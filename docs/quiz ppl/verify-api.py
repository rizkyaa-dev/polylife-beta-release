"""Exercise the isolated Docker API and record responses without bearer tokens."""

import argparse
import json
import urllib.error
import urllib.request
from pathlib import Path


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--read-only', action='store_true')
    args = parser.parse_args()
    directory = Path(__file__).resolve().parent
    results = []

    def request(method, path, payload=None, token=None, expected=200):
        url = 'http://127.0.0.1:8088/api/v1' + path
        headers = {'Accept': 'application/json', 'Content-Type': 'application/json'}
        if token:
            headers['Authorization'] = 'Bearer ' + token
        req = urllib.request.Request(
            url, headers=headers, method=method,
            data=json.dumps(payload).encode() if payload is not None else None,
        )
        try:
            response = urllib.request.urlopen(req, timeout=30)
        except urllib.error.HTTPError as error:
            response = error
        with response:
            status, body = response.status, json.load(response)
        if status != expected:
            raise RuntimeError(f'{method} {path}: expected {expected}, got {status}: {body}')
        sanitized = json.loads(json.dumps(body))
        if isinstance(sanitized.get('data'), dict):
            sanitized['data'].pop('access_token', None)
        results.append({'method': method, 'url': url, 'status': status, 'response': sanitized})
        print(f'{method} {path} -> HTTP {status}')
        return body

    login = request('POST', '/auth/login', {
        'email': 'quiz.docker@example.test', 'password': 'QuizDocker!2405097', 'device_name': 'quiz-cli',
    })
    token = login['data']['access_token']
    try:
        request('GET', '/auth/me', token=token)
        if args.read_only:
            note_id = int((directory / 'logs/persisted-note-id.txt').read_text())
        else:
            note = request('POST', '/catatan', {
                'judul': 'Bukti Docker PolyLife 2405097',
                'isi': 'Bukti API tersimpan di MySQL Docker dengan named volume db_data.',
                'tanggal': '2026-10-09', 'show_preview': True,
            }, token=token, expected=201)
            note_id = note['data']['id']
            (directory / 'logs/persisted-note-id.txt').write_text(str(note_id))
        note = request('GET', f'/catatan/{note_id}', token=token)
        if note['data']['judul'] != 'Bukti Docker PolyLife 2405097':
            raise RuntimeError('Stored note does not match the submitted data.')
        request('POST', '/catatan', {}, token=token, expected=422)
    finally:
        request('POST', '/auth/logout', {}, token=token)
    suffix = 'after-restart' if args.read_only else 'docker'
    (directory / f'logs/api-{suffix}-results.json').write_text(
        json.dumps(results, indent=2, ensure_ascii=False), encoding='utf-8',
    )
    print(f'PASS: catatan ID {note_id}, data sesuai, token demo telah dicabut.')


if __name__ == '__main__':
    main()
