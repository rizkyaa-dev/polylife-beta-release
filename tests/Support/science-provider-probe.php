<?php

use App\Services\Ai\DTOs\LlmRequestOptions;
use App\Services\Ai\Enums\ThinkingEffort;
use App\Services\Ai\Science\AiScienceAgent;
use Illuminate\Contracts\Console\Kernel;

// Explicit manual paid probe; no users, conversations or workspace records are created.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$application = require dirname(__DIR__, 2).'/bootstrap/app.php';
$application->make(Kernel::class)->bootstrap();
$case = $argv[1] ?? 'equilibrium';
$problem = match ($case) {
    'equilibrium' => 'Solve a static three-coordinate spring equilibrium with SI units. Unknowns x1,x2,x3 are displacements in metres. The stiffness matrix in N/m is [[400,-100,0],[-100,300,-100],[0,-100,200]], force vector in N is [10,0,5]. Give the displacement vector, assumptions and numerical residual verification. Do not invent geometry or additional forces.',
    'circuit' => 'A three-mesh circuit has current unknowns I1,I2,I3 in amperes. In SI units the resistance matrix is [[12,-4,-2],[-4,15,-3],[-2,-3,10]] ohms and voltage vector is [24,-6,12] volts. Solve the coupled equations. State current order, sign interpretation and numerical verification limits.',
    'integration' => 'Compute the work W in joules for force F(x)=10*x*exp(-x*x) newtons with x numerically expressed in metres, from x=0 to x=2 metres. The coefficients have the implied SI units needed for this specified force law. Use numerical integration with absolute tolerance 1e-7 and disclose estimated error limitations; do not invent a closed-form solver.',
    'oscillator' => 'A damped harmonic oscillator has mass 1 kg, damping 0.4 kg/s and stiffness 4 N/m. Initial displacement 1 m, initial velocity 0 m/s, no forcing. Numerically integrate to t=5 seconds. State order displacement then velocity, normalize to SI, provide dimensional AST declarations and report numerical error limitations.',
    'oscillator-energy' => 'For an unforced damped harmonic oscillator m=1 kg, damping=0.4 kg/s, stiffness=4 N/m, x(0)=1 m and v(0)=0 m/s, compute the displacement, velocity and total mechanical energy E=0.5*m*v^2+0.5*k*x^2 at t=5 seconds. Use a nonstiff numerical IVP, state order displacement then velocity, structured SI dimensions, and a declared computed output named energy in joules. Do not hand-calculate or retype a candidate energy; compute it from the final solver states. State the limitations of local error estimates and physical modeling.',
    'resonance' => 'A forced undamped spring-mass system has m=1 kg, k=4 N/m, x(0)=0 m, v(0)=0 m/s. The applied force is F(t)=3*sin(2*t) N, with angular frequency 2 radians/second and t in seconds. Numerically solve the resonant transient through t=5 seconds, state order [x,v], componentwise absolute local tolerance 1e-8 in each state SI unit. Supply structured dimensional declarations including the sinusoidal argument. Do not invent damping or use steady-state amplitude at resonance; disclose model and numerical verification limits.',
    'radiative-equilibrium' => 'A small isothermal object exchanges heat only by radiation with a large environment at 300 K. Its emitting area is 0.01 m^2, constant emissivity 0.8 and absorbed heater power 40 W. Use sigma=5.670374419e-8 W/(m^2*K^4). Determine the positive steady equilibrium temperature from 40=epsilon*sigma*A*(T^4-300^4), in the range 300..1000 K, to an absolute temperature target 1e-5 K. No convection or conduction. No transient trajectory or heat capacity is specified or requested; do not invent those or silently linearize this equilibrium law.',
    'rc-network' => 'Solve this two-node RC transient with state order [V1,V2]. A constant 10 V ideal source connects through R1=1 kiloohm to node 1. R2=2 kiloohms connects node 1 to node 2; R3=3 kiloohms connects node 2 to ground. C1=100 microfarads connects node 1 to ground, C2=220 microfarads connects node 2 to ground. Both capacitor voltages initially zero at t=0. Report both voltages at t=2 seconds with absolute local target 1e-8 in volts. Explicitly normalize kiloohms and microfarads to SI; derive KCL without inventing components, provide structured dimensions. No arbitrary scripts.',
    'missing' => 'Determine equilibrium displacements of three coupled springs under a 10 N load. Spring stiffnesses, connectivity and boundary constraints are not given. Ask for essential information instead of assuming values.',
    'unsupported' => 'Solve the three-dimensional nonlinear incompressible Navier-Stokes equations for arbitrary turbulent flow exactly. No domain geometry, initial field, boundary conditions or fluid properties are given. Do not replace this with a fabricated linear system.',
    default => throw new InvalidArgumentException('Unknown scientific probe case'),
};
$started = microtime(true);
try {
    $agent = app(AiScienceAgent::class);
    $options = new LlmRequestOptions(ThinkingEffort::Low, 90, 8192);
    $plan = $agent->plan(['problem' => $problem], $options);
    $result = $agent->complete($plan, $options);
    $oracle = null;
    if ($case === 'radiative-equilibrium') {
        if ($result['status'] === 'unsupported' && $result['result'] === null && $result['solver_inputs'] === null) {
            $oracle = ['status' => 'unsupported_method_boundary_checked', 'reason' => 'No registered generic nonlinear root solver.'];
        } else {
            // This law also admits an exact algebraic reduction; don't force refusal.
            $expected = (40 / (0.8 * 5.670374419e-8 * 0.01) + 300 ** 4) ** 0.25;
            $actual = $result['result']['outputs'][0]['value'] ?? $result['result']['value'] ?? $result['result']['solution'][0] ?? $result['result']['final_state'][0] ?? null;
            $dimension = $result['result']['outputs'][0]['dimension'] ?? ($result['solver'] === 'root_scalar'
                ? ($result['solver_inputs']['dimensions']['x'] ?? null) : null) ?? $result['solver_inputs']['dimensions']['output'] ?? $result['solver_inputs']['dimensions']['variables'][0]
                ?? $result['solver_inputs']['dimensions']['states'][0] ?? null;
            if (($result['result']['status'] ?? null) !== 'computed' || ! is_numeric($actual)
                || abs($actual - $expected) > 1e-5 || $dimension != [0, 0, 0, 0, 1, 0, 0]
                || ($result['result']['dimension_verification']['status'] ?? null) !== 'declared_dimensions_checked') {
                throw new RuntimeException('Radiative temperature disagrees with the analytic Kelvin oracle.');
            }
            if ($result['solver'] === 'ode_ivp') {
                // Power continuation is a valid exact reduction, not invented time dynamics.
                if ($result['solver_inputs']['dimensions']['t'] != [1, 2, -3, 0, 0, 0, 0]
                    || $result['solver_inputs']['t_start'] != 0 || $result['solver_inputs']['t_end'] != 40) {
                    throw new RuntimeException('Radiative continuation must use absorbed power, not an invented physical time.');
                }
                foreach ($result['result']['samples'] as $sample) {
                    $temperature = ($sample['t'] / (0.8 * 5.670374419e-8 * 0.01) + 300 ** 4) ** 0.25;
                    if (abs($sample['state'][0] - $temperature) > 1e-5) {
                        throw new RuntimeException('Radiative continuation trajectory disagrees with the exact equilibrium family.');
                    }
                }
            }
            $oracle = ['status' => 'independent_radiative_equilibrium_reference_passed', 'expected_kelvin' => $expected];
        }
    }
    if ($case === 'resonance') {
        // Independent exact resonant solution, not the numerical solver's output.
        $time = 5.0;
        $expected = [3 / 8 * sin(2 * $time) - 3 / 4 * $time * cos(2 * $time),
            3 / 2 * $time * sin(2 * $time)];
        $actual = $result['result']['final_state'] ?? null;
        if ($result['solver'] !== 'ode_ivp' || ! is_array($actual) || count($actual) !== 2
            || max(abs($actual[0] - $expected[0]), abs($actual[1] - $expected[1])) > 1e-5
            || ($result['result']['dimension_verification']['status'] ?? null) !== 'declared_dimensions_checked') {
            throw new RuntimeException('Live resonant model disagrees with independent analytic reference or lacks checked dimensions.');
        }
        $oracle = ['status' => 'independent_resonance_reference_passed', 'expected_state_si' => $expected];
    }
    if (in_array($case, ['oscillator', 'oscillator-energy'], true)) {
        $alpha = 0.2;
        $frequency = sqrt(4 - $alpha ** 2);
        $time = 5.0;
        $decay = exp(-$alpha * $time);
        $expected = [$decay * (cos($frequency * $time) + $alpha / $frequency * sin($frequency * $time)),
            -4 / $frequency * $decay * sin($frequency * $time)];
        $actual = $result['result']['final_state'] ?? null;
        if ($result['solver'] !== 'ode_ivp' || ! is_array($actual) || count($actual) !== 2
            || max(abs($actual[0] - $expected[0]), abs($actual[1] - $expected[1])) > 1e-5
            || ($result['result']['dimension_verification']['status'] ?? null) !== 'declared_dimensions_checked') {
            throw new RuntimeException('Live oscillator disagrees with independent analytic reference or lacks checked dimensions.');
        }
        $oracle = ['status' => 'independent_damped_oscillator_reference_passed', 'expected_state_si' => $expected];
        if ($case === 'oscillator-energy') {
            $energy = collect($result['result']['outputs'] ?? [])->firstWhere('name', 'energy');
            $expectedEnergy = 0.5 * $expected[1] ** 2 + 2 * $expected[0] ** 2;
            if (! is_array($energy) || abs($energy['value'] - $expectedEnergy) > 1e-5
                || $energy['dimension'] != [1, 2, -2, 0, 0, 0, 0]) {
                throw new RuntimeException('Computed mechanical energy disagrees with the independent analytic oracle.');
            }
            $oracle['expected_energy_joules'] = $expectedEnergy;
        }
    }
    if ($case === 'integration') {
        $expected = 5 * (1 - exp(-4));
        $actual = $result['result']['value'] ?? null;
        if ($result['solver'] !== 'integrate' || ! is_numeric($actual) || abs($actual - $expected) > 1e-7
            || ($result['result']['dimension_verification']['status'] ?? null) !== 'declared_dimensions_checked') {
            throw new RuntimeException('Live work model disagrees with independent analytic integral or lacks checked dimensions.');
        }
        $oracle = ['status' => 'independent_analytic_integral_reference_passed', 'expected_joules' => $expected];
    }
    if ($case === 'rc-network') {
        // Independent closed-form exponential of the specified 2x2 KCL system.
        $a = -15.0;
        $b = 5.0;
        $c = 1 / (2000 * 220e-6);
        $d = -(1 / 2000 + 1 / 3000) / 220e-6;
        $q = ($a + $d) / 2;
        $r = sqrt((($a - $d) / 2) ** 2 + $b * $c);
        $factor = exp(2 * $q);
        $ch = cosh(2 * $r);
        $sh = sinh(2 * $r) / $r;
        $steady = [25 / 3, 5.0];
        $expected = [
            $steady[0] - $factor * (($ch + $sh * ($a - $q)) * $steady[0] + $sh * $b * $steady[1]),
            $steady[1] - $factor * ($sh * $c * $steady[0] + ($ch + $sh * ($d - $q)) * $steady[1]),
        ];
        $actual = $result['result']['final_state'] ?? null;
        if ($result['solver'] !== 'ode_ivp' || ! is_array($actual) || count($actual) !== 2
            || max(abs($actual[0] - $expected[0]), abs($actual[1] - $expected[1])) > 1e-6
            || ($result['result']['dimension_verification']['status'] ?? null) !== 'declared_dimensions_checked') {
            throw new RuntimeException('Live RC model disagrees with independent KCL oracle or lacks checked dimensions.');
        }
        $oracle = ['status' => 'independent_KCL_analytic_reference_passed', 'expected_volts' => $expected];
    }
    $summaryOnly = in_array('--summary', $argv, true);
    $reported = $result;
    if ($summaryOnly) {
        unset($reported['solver_inputs'], $reported['result']['samples']);
    }
    echo json_encode(['case' => $case, 'duration_ms' => (int) round((microtime(true) - $started) * 1000),
        'independent_oracle' => $oracle, 'result' => $reported], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $exception) {
    echo json_encode(['case' => $case, 'failure' => get_class($exception), 'message' => $exception->getMessage(),
        'diagnostic_plan' => $result ?? $plan ?? null], JSON_THROW_ON_ERROR).PHP_EOL;
    exit(1);
}
