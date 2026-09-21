<?php

namespace App\Services\Ai\Science;

use Illuminate\Validation\ValidationException;

final class ScienceSolverRegistry
{
    public const KERNEL_VERSION = 'science-kernel-1.8';

    public function __construct(private readonly LinearSystemSolver $linear, private readonly IntegrationSolver $integration,
        private readonly OdeSolver $ode, private readonly RootScalarSolver $root,
        private readonly DimensionVerifier $dimensions,
        private readonly ScienceOutputProjector $outputs, private readonly ScienceUnitConverter $conversions) {}

    public function specifications(): array
    {
        $dimensions = ['basis' => DimensionVerifier::ORDER,
            'representation' => 'Seven-number SI exponent vector in basis order. E.g. length [0,1,0,0,0,0,0], force [1,1,-2,0,0,0,0], energy [1,2,-2,0,0,0,0]. All values normalized to SI before solving.',
            'integrate' => 'inputs.dimensions = {x:vector,output:vector}. Each dimensional constant AST node has dimension:vector. Output = integrand dimension + x dimension.',
            'root_scalar' => 'inputs.dimensions = {x:vector,output:vector}. Expression dimension must equal output. The tolerance is an absolute target in the x unit.',
            'ode_ivp' => 'inputs.dimensions = {t:vector,states:[vector,...]}. Derivative i must have state i dimension minus time dimension. Dimensional constants need dimension vectors.',
            'linear_system' => 'inputs.dimensions = {variables:[vector,...],rhs:[vector,...],matrix:[[vector,...],...]}. Every matrix[i][j] dimension plus variable[j] dimension must equal rhs[i] dimension.',
            'limitations' => 'Declaring all quantities dimensionless is not legitimate for dimensional physical models. If dimensions cannot be established, disclose them as unverified; never fabricate declarations.'];

        return array_map(fn ($solver) => array_merge($solver->specification(), ['dimensional_contract' => $dimensions,
            'conversion_contract' => 'If the user supplies non-SI values, first store the converted SI number in the solver field, then bind the original value to that exact numeric path: {path:list,source_value:number,source_unit:code}. This is verification metadata, not a transformer. Every AST path must end in "value". Example: a 2 kohm const is {op:"const",value:2000,dimension:[1,2,-3,-2,0,0,0]} with conversion {path:["derivatives",0,"args",1,"value"],source_value:2,source_unit:"kohm"}; 100 uF is stored as 0.0001 F. Repeat a conversion entry for every duplicated AST occurrence. Supported codes: m,cm,mm,km,s,ms,min,h,kg,g,K,degC,A,mA,N,J,W,Pa,V,ohm,kohm,F,uF,rad/s. Paths may target linear matrix/rhs values, integration bounds/constant AST values, ODE initial/time/constant AST values, or output constant AST values. The trusted registry checks scale/offset and declared dimension. Use [] only when no source conversion was needed; omission stays unverified.',
            'optional_outputs' => 'inputs.outputs: 1..8 {name,expression,dimension} entries. Name is unique ASCII letter followed by <=63 letters/digits/underscores. expression is the bounded arithmetic AST. r0..rN bind computed solution entries (linear), r0 the integral/root value, or final states (ODE). No chaining between outputs. Structured input dimensions are required. Every output must be numerically dependent on at least one solver slot under bounded perturbation. Constants must not contain a candidate answer. Derived outputs inherit solver accuracy limitations.']), [$this->linear, $this->integration, $this->ode, $this->root]);
    }

    public function solve(string $name, array $inputs): array
    {
        foreach ([$this->linear, $this->integration, $this->ode, $this->root] as $solver) {
            if ($name === $solver->name()) {
                $conversionVerification = $this->conversions->verify($name, $inputs);
                $result = $solver->solve($inputs);
                $result['dimension_verification'] = $this->dimensions->verify($name, $inputs);
                $result['conversion_verification'] = $conversionVerification;
                $result['kernel_version'] = self::KERNEL_VERSION;

                return $this->outputs->project($name, $inputs, $result);
            }
        }
        throw ValidationException::withMessages(['solver' => 'Solver is unavailable; never invent numerical results.']);
    }

    /** Structural preflight only: no numerical solve or candidate answer is produced. */
    public function validateContract(string $name, array $inputs): void
    {
        if (! in_array($name, array_column($this->specifications(), 'name'), true)) {
            throw ValidationException::withMessages(['solver' => 'Solver is unavailable; never invent numerical results.']);
        }
        $this->conversions->verify($name, $inputs);
        $dimensionVerification = $this->dimensions->verify($name, $inputs);
        $this->outputs->project($name, $inputs, ['status' => 'not_computed', 'dimension_verification' => $dimensionVerification]);
    }
}
