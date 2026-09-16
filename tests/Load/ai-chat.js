import http from 'k6/http';
import { check, sleep } from 'k6';
import { Trend, Rate } from 'k6/metrics';

const baseUrl = (__ENV.BASE_URL || 'http://127.0.0.1:8000').replace(/\/$/, '');
const password = __ENV.PASSWORD;
const runDuration = new Trend('ai_run_duration', true);
const runFailures = new Rate('ai_run_failures');

export const options = {
    vus: Number(__ENV.K6_VUS || 1),
    duration: __ENV.K6_DURATION || '1m',
    thresholds: {
        http_req_failed: ['rate<0.01'],
        http_req_duration: ['p(95)<1000'],
        ai_run_failures: ['rate<0.02'],
        ai_run_duration: ['p(95)<30000'],
    },
};

export function setup() {
    if ((!__ENV.EMAIL && !__ENV.EMAIL_TEMPLATE) || !password) {
        throw new Error('EMAIL (or EMAIL_TEMPLATE) and PASSWORD are required.');
    }
}

export default function () {
    const email = (__ENV.EMAIL_TEMPLATE || __ENV.EMAIL).replace('{vu}', String(__VU));
    const loginPage = http.get(`${baseUrl}/login`);
    const loginToken = hiddenCsrf(loginPage.body);
    const login = http.post(`${baseUrl}/login`, {
        _token: loginToken,
        email,
        password,
    }, { redirects: 0 });
    check(login, { 'login accepted': response => [302, 303].includes(response.status) });

    const workspace = http.get(`${baseUrl}/workspace/ai?new=1`);
    const csrf = metaCsrf(workspace.body);
    const requestId = randomUuid();
    const accepted = http.post(`${baseUrl}/workspace/ai/chat`, JSON.stringify({
        message: 'Jelaskan perbedaan autentikasi dan otorisasi tanpa membaca data workspace.',
        request_id: requestId,
    }), {
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf,
        },
    });
    if (!check(accepted, { 'chat accepted': response => response.status === 202 })) {
        runFailures.add(true);
        return;
    }

    const startedAt = Date.now();
    const runId = accepted.json('run_id');
    for (let attempt = 0; attempt < 60; attempt++) {
        const status = http.get(`${baseUrl}/workspace/ai/runs/${runId}`, {
            headers: { Accept: 'application/json' },
        });
        if (status.status === 200 && status.json('status') === 'success') {
            runDuration.add(Date.now() - startedAt);
            runFailures.add(false);
            sleep(1);
            return;
        }
        if (status.json('status') === 'failed') {
            runFailures.add(true);
            return;
        }
        sleep(Math.min(8, 1 + Math.floor(attempt / 5)));
    }

    runFailures.add(true);
}

function hiddenCsrf(body) {
    return body.match(/name="_token"\s+value="([^"]+)"/)?.[1]
        || body.match(/value="([^"]+)"\s+name="_token"/)?.[1]
        || '';
}

function metaCsrf(body) {
    return body.match(/name="csrf-token"\s+content="([^"]+)"/)?.[1] || '';
}

function randomUuid() {
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, character => {
        const random = Math.floor(Math.random() * 16);
        const value = character === 'x' ? random : (random & 0x3) | 0x8;
        return value.toString(16);
    });
}
