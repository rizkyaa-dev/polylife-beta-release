<?php

namespace App\Services\Ai\Science\Models;

use Illuminate\Validation\ValidationException;

/** Allowlisted Strategy registry. No class names, code, or plugins are loaded from model data. */
final class ScienceModelRegistry
{
    public const VERSION = 'science-models-1';

    private readonly array $validators;

    public function __construct(RcLinearModelValidator $rc)
    {
        $this->validators = [$rc->domain() => $rc];
    }

    public function specifications(): array
    {
        return array_map(fn (ScienceModelValidator $validator) => $validator->specification(), array_values($this->validators));
    }

    public function prepare(array $plan): array
    {
        // Never inherit a model/provider/client claim or a previously cached assessment.
        $plan['model_verification'] = 'unverified';
        $plan['model_evidence'] = ['version' => self::VERSION, 'status' => 'unverified',
            'checks' => [], 'limitations' => 'No supported domain model was supplied; solver checks do not verify physical formulation.'];
        $model = $plan['domain_model'] ?? null;
        if (($plan['status'] ?? null) !== 'ready') {
            $plan['domain_model'] = null;
            $plan['solver_inputs'] = null;
            $plan['solver'] = null;
            $plan['result'] = null;

            return $plan;
        }
        if ($model === null) {
            return $plan;
        }
        if (! is_array($model) || array_is_list($model) || strlen(json_encode($model, JSON_THROW_ON_ERROR)) > 16000
            || ($model['version'] ?? null) !== 'model-v1' || ! is_string($model['domain'] ?? null)) {
            throw ValidationException::withMessages(['domain_model' => 'A bounded model-v1 domain object is required.']);
        }
        $validator = $this->validators[$model['domain']] ?? null;
        if ($validator === null) {
            throw ValidationException::withMessages(['domain_model' => 'Domain is unavailable. Omit domain_model to use the explicitly unverified generic path.']);
        }
        $source = $plan['model_source'] ?? null;
        if (! is_string($source) || $source === '' || mb_strlen($source) > 8000) {
            throw ValidationException::withMessages(['domain_model' => 'Backend-retained source text is required for domain preparation.']);
        }
        $prepared = $validator->prepare($model, $source);
        if (($plan['solver'] ?? null) !== $prepared->solver) {
            throw ValidationException::withMessages(['solver' => 'The selected domain requires '.$prepared->solver.'.']);
        }
        // Authoritative equations come from the domain builder, not the proposal AST.
        $plan['solver_inputs'] = $prepared->inputs;
        $plan['model_evidence'] = ['version' => self::VERSION, 'validator' => $validator->domain(),
            'validator_version' => $validator->specification()['version'],
            'status' => 'declared_structure_checked', 'equations_authority' => 'deterministic_domain_builder',
            'state_order' => $prepared->stateOrder, 'checks' => $prepared->checks,
            'fingerprint' => hash('sha256', ModelCanonicalJson::encode([self::VERSION, $validator->specification()['version'],
                $model, hash('sha256', $source), $prepared->inputs])),
            'limitations' => 'Checks the declared idealized topology and source-quote presence. Does not prove that the declaration captures all user facts, requested scope, source semantics, real components, or physical reality. Numerical applicability and accuracy remain subject to solver limits.'];

        return $plan;
    }
}
