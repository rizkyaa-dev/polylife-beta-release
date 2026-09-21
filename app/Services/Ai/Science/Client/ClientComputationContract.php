<?php

namespace App\Services\Ai\Science\Client;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class ClientComputationContract
{
    public const VERSION = 'client-js-1';

    public function validate(array $proposal, array $outputNames = []): array
    {
        if (($proposal['status'] ?? null) !== 'ready') {
            unset($proposal['program']);
        }
        if (($proposal['status'] ?? null) !== 'needs_clarification') {
            unset($proposal['question']);
        }
        $data = Validator::make($proposal, [
            'status' => ['required', 'in:ready,needs_clarification,unsupported'],
            'model' => ['required', 'string', 'max:3000'],
            'assumptions' => ['present', 'array', 'max:12'], 'assumptions.*' => ['string', 'max:500'],
            'units' => ['present', 'array', 'max:32'], 'units.*' => ['string', 'max:200'],
            'coverage' => ['sometimes', 'in:full,partial'],
            'partial_scope' => ['required_if:coverage,partial', 'array:completed_tasks,deferred_tasks,outputs,reason'],
            'partial_scope.completed_tasks' => ['required_if:coverage,partial', 'array', 'min:1', 'max:15'],
            'partial_scope.completed_tasks.*' => ['string', 'max:200'],
            'partial_scope.deferred_tasks' => ['required_if:coverage,partial', 'array', 'min:1', 'max:15'],
            'partial_scope.deferred_tasks.*' => ['string', 'max:200'],
            'partial_scope.outputs' => ['required_if:coverage,partial', 'array', 'min:1', 'max:32'],
            'partial_scope.outputs.*' => ['required', 'string', 'max:128', 'distinct:strict'],
            'partial_scope.reason' => ['required_if:coverage,partial', 'string', 'max:1000'],
            'question' => ['required_if:status,needs_clarification', 'string', 'max:1000'],
            'program' => ['required_if:status,ready', 'array:source,inputs,checks'],
            'program.source' => ['required_if:status,ready', 'string', 'max:12000'],
            'program.inputs' => [($proposal['status'] ?? null) === 'ready' ? 'present' : 'sometimes', 'array'],
            'program.checks' => ['required_if:status,ready', 'array', 'min:1', 'max:8'],
            'program.checks.*' => ['string', 'max:200'],
        ])->validate();
        if ($data['status'] !== 'ready') {
            unset($data['program']);

            return $data;
        }
        $this->boundedJson($data['program']['inputs']);
        if (strlen($data['program']['source']) > 12000 || strlen(json_encode($data['program']['inputs'], JSON_THROW_ON_ERROR)) > 8192) {
            throw ValidationException::withMessages(['program' => 'Client program exceeds its resource limit.']);
        }
        $data['program']['version'] = self::VERSION;
        if (($data['coverage'] ?? null) === 'partial') {
            $data['partial_scope']['deferred_requested_outputs'] = array_values(array_diff($outputNames, $data['partial_scope']['outputs']));
            $outputNames = $data['partial_scope']['outputs'];
        }
        if ($outputNames !== []) {
            $data['program']['output_names'] = $outputNames;
        }
        $data['program']['hash'] = hash('sha256', json_encode($data['program'], JSON_THROW_ON_ERROR));

        return $data;
    }

    /** Validate data shape, not scientific truth or proof that a client ran the program. */
    public function receipt(array $plan, ?array $candidate, ?string $failure): array
    {
        $base = $plan;
        unset($base['program'], $base['model_source']);
        $base['execution'] = ['requested_runner' => 'browser', 'reported_runner' => 'quickjs-wasm',
            'authoritative_runner' => null, 'verification_method' => 'none',
            'program_hash' => $plan['program']['hash'], 'client_failure' => $failure];
        $base['model_verification'] = 'unverified';
        $base['model_evidence'] = ['status' => 'unverified', 'checks' => [],
            'limitations' => 'Client execution and reported checks are not independently verified.'];
        if ($failure !== null || $candidate === null) {
            $base['status'] = 'unsupported';
            $base['result'] = null;
            $base['execution']['client_failure'] ??= 'unavailable';

            return $base;
        }
        $candidate = Validator::make($candidate, [
            'values' => ['required', 'array', 'min:1', 'max:32'],
            'checks' => ['present', 'array', 'max:8'],
            'checks.*' => ['array:name,passed,residual'],
            'checks.*.name' => ['required', 'string', 'max:200'],
            'checks.*.passed' => ['required', 'boolean'],
            'checks.*.residual' => ['sometimes', 'numeric'],
        ])->validate();
        $this->boundedJson($candidate);
        if (isset($plan['program']['output_names'])) {
            $actualNames = array_keys($candidate['values']);
            $expectedNames = $plan['program']['output_names'];
            sort($actualNames);
            sort($expectedNames);
            if ($actualNames !== $expectedNames) {
                throw ValidationException::withMessages(['result.values' => 'Client output names do not match the requested contract.']);
            }
        }
        if (strlen(json_encode($candidate, JSON_THROW_ON_ERROR)) > 16384) {
            throw ValidationException::withMessages(['result' => 'Client result is too large.']);
        }
        if ($candidate['checks'] === [] || array_filter($candidate['checks'], fn (array $check): bool => ! (bool) $check['passed']) !== []) {
            // Even unverified client reports must not masquerade as a successful checked run.
            $base['status'] = 'unsupported';
            $base['result'] = null;
            $base['execution']['client_failure'] = 'invalid_input';
            $base['execution']['reported_check_status'] = $candidate['checks'] === [] ? 'missing' : 'failed';

            return $base;
        }
        $base['status'] = 'client_computed';
        $base['result'] = ['status' => 'client_computed', 'values' => $candidate['values'],
            'reported_checks' => $candidate['checks'], 'verification' => ['status' => 'client_reported_only'],
            'limitations' => 'Model, algorithm, accuracy and execution are unverified; reported checks are client claims, not certificates.'];

        return $base;
    }

    private function boundedJson(array $data): void
    {
        $count = 0;
        $walk = function (mixed $value, int $depth) use (&$walk, &$count): void {
            if (++$count > 2048 || $depth > 12 || (is_float($value) && ! is_finite($value)) || is_object($value) || is_resource($value)) {
                throw ValidationException::withMessages(['program' => 'Invalid or excessive client JSON data.']);
            }
            if (is_array($value)) {
                foreach ($value as $item) {
                    $walk($item, $depth + 1);
                }
            }
        };
        $walk($data, 0);
    }
}
