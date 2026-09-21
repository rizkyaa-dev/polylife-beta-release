<?php

namespace App\Services\Ai\Science\Models;

use App\Services\Ai\Science\ScienceNumbers;
use Illuminate\Validation\ValidationException;

/** Builds C*dV/dt = sum((V_neighbor-V)/R) for bounded grounded-capacitor RC networks. */
final class RcLinearModelValidator implements ScienceModelValidator
{
    private const VOLT = [1, 2, -3, -1, 0, 0, 0];

    private const OHM = [1, 2, -3, -2, 0, 0, 0];

    private const FARAD = [-1, -2, 4, 2, 0, 0, 0];

    private const SECOND = [0, 0, 1, 0, 0, 0, 0];

    public function __construct(private readonly SourceQuantityBinder $quantities) {}

    public function domain(): string
    {
        return 'rc_linear';
    }

    public function specification(): array
    {
        return ['domain' => $this->domain(), 'version' => 'rc-linear-1', 'solver' => 'ode_ivp',
            'schema' => 'domain_model={version:"model-v1",domain:"rc_linear",nodes:[{id,kind:"ground"}|{id,kind:"fixed",voltage:quantity}|{id,kind:"dynamic",initial:quantity}],components:[{id,type:"resistor|capacitor",from:node_id,to:node_id,value:quantity,quote:exact_source_quote}],t_start:quantity,t_end:quantity,tolerance:number,outputs:[dynamic_node_id,...]}. quantity={value:number,unit:code,quote:exact_source_quote}. Quotes must be nonempty verbatim substrings of original_problem; do not invent measurements or quotes.',
            'limits' => ['nodes' => 9, 'dynamic_nodes' => 4, 'components' => 16],
            'limitations' => 'Ideal positive resistors, exactly one positive grounded capacitor per dynamic node, constant ideal fixed-node voltages, one ground, explicit initial voltages and time bounds only. No floating/coupling/parallel capacitors, inductors, switching, nonlinear devices or current sources. Outputs are selected dynamic-node voltages only. Unsupported topologies must use the unverified generic path or return unsupported. The builder checks the declared topology, not whether text interpretation is correct. Domain plans omit inputs: the server builds them.'];
    }

    public function prepare(array $model, string $originalProblem): PreparedScienceModel
    {
        $this->keys($model, ['version', 'domain', 'nodes', 'components', 't_start', 't_end', 'tolerance', 'outputs']);
        $nodes = $this->items($model['nodes'] ?? null, 1, 9);
        $components = $this->items($model['components'] ?? null, 1, 16);
        $outputs = $this->items($model['outputs'] ?? null, 1, 4);
        $byId = $dynamic = $fixed = $initial = [];
        $ground = null;
        foreach ($nodes as $node) {
            if (! is_array($node)) {
                $this->invalid('Nodes must be objects.');
            }
            $kind = $node['kind'] ?? null;
            $this->keys($node, match ($kind) {
                'ground' => ['id', 'kind'], 'fixed' => ['id', 'kind', 'voltage'],
                'dynamic' => ['id', 'kind', 'initial'], default => $this->invalid('Unsupported node kind.'),
            });
            $id = $this->identifier($node['id'] ?? null);
            if (isset($byId[$id])) {
                $this->invalid('Node identifiers must be unique.');
            }
            $byId[$id] = $kind;
            if ($kind === 'ground') {
                if ($ground !== null) {
                    $this->invalid('Exactly one ground is supported.');
                }
                $ground = $id;
            } elseif ($kind === 'fixed') {
                $fixed[$id] = $this->quantity($node['voltage'] ?? null, self::VOLT, $originalProblem, null, 'nodes.'.$id.'.voltage');
            } else {
                $dynamic[$id] = count($dynamic);
                $initial[$id] = $this->quantity($node['initial'] ?? null, self::VOLT, $originalProblem, 'initial_voltage', 'nodes.'.$id.'.initial');
            }
        }
        if ($ground === null || count($dynamic) < 1 || count($dynamic) > 4) {
            $this->invalid('One ground and one to four dynamic nodes are required.');
        }
        $capacitors = $resistors = $ids = [];
        foreach ($components as $component) {
            if (! is_array($component)) {
                $this->invalid('Components must be objects.');
            }
            $this->keys($component, ['id', 'type', 'from', 'to', 'value', 'quote']);
            $id = $this->identifier($component['id'] ?? null);
            if (isset($ids[$id])) {
                $this->invalid('Component identifiers must be unique.');
            }
            $ids[$id] = true;
            $from = $this->identifier($component['from'] ?? null);
            $to = $this->identifier($component['to'] ?? null);
            if (! isset($byId[$from], $byId[$to]) || $from === $to) {
                $this->invalid('Components require two distinct declared endpoints.');
            }
            $this->quote($component['quote'] ?? null, $originalProblem, 'components.'.$id.'.quote');
            $type = $component['type'] ?? null;
            if (! in_array($type, ['resistor', 'capacitor'], true)) {
                $this->invalid('Unsupported component type.');
            }
            $quantity = $this->quantity($component['value'] ?? null, $type === 'resistor' ? self::OHM : self::FARAD, $originalProblem, null, 'components.'.$id.'.value');
            if ($quantity['si_value'] <= 0) {
                $this->invalid('Resistance and capacitance must be positive.');
            }
            if ($type === 'capacitor') {
                $state = $from === $ground ? $to : ($to === $ground ? $from : null);
                if ($state === null || ! isset($dynamic[$state]) || isset($capacitors[$state])) {
                    $this->invalid('Exactly one grounded capacitor per dynamic node is supported.');
                }
                $capacitors[$state] = $quantity;
            } else {
                if (! isset($dynamic[$from]) && ! isset($dynamic[$to])) {
                    $this->invalid('Every resistor must connect to a dynamic node.');
                }
                $resistors[] = ['from' => $from, 'to' => $to, 'quantity' => $quantity];
            }
        }
        if (count($capacitors) !== count($dynamic)) {
            $this->invalid('Every dynamic node requires an explicit grounded capacitor.');
        }
        $start = $this->quantity($model['t_start'] ?? null, self::SECOND, $originalProblem, 'time_origin', 't_start');
        $end = $this->quantity($model['t_end'] ?? null, self::SECOND, $originalProblem, null, 't_end');
        if ($end['si_value'] <= $start['si_value'] || ! is_finite($end['si_value'] - $start['si_value'])) {
            $this->invalid('A finite increasing time interval is required.');
        }
        $tolerance = ScienceNumbers::finite($model['tolerance'] ?? null);
        if ($tolerance < 1e-8 || $tolerance > 1e-2) {
            $this->invalid('The absolute local voltage target must be 1e-8..1e-2 V.');
        }
        if (count(array_unique($outputs, SORT_REGULAR)) !== count($outputs)) {
            $this->invalid('Output nodes must be unique.');
        }
        $selected = [];
        foreach ($outputs as $output) {
            $id = $this->identifier($output);
            if (! isset($dynamic[$id])) {
                $this->invalid('Only declared dynamic-node voltages can be selected as outputs.');
            }
            $selected[] = ['name' => $id, 'expression' => ['op' => 'var', 'name' => 'r'.$dynamic[$id]], 'dimension' => self::VOLT];
        }
        $derivatives = [];
        foreach ($dynamic as $id => $index) {
            $currents = [];
            foreach ($resistors as $resistor) {
                if ($resistor['from'] !== $id && $resistor['to'] !== $id) {
                    continue;
                }
                $neighbor = $resistor['from'] === $id ? $resistor['to'] : $resistor['from'];
                $voltage = isset($dynamic[$neighbor]) ? ['op' => 'var', 'name' => 'y'.$dynamic[$neighbor]]
                    : ($neighbor === $ground ? ['op' => 'const', 'value' => 0.0, 'dimension' => self::VOLT]
                        : $this->constant($fixed[$neighbor], self::VOLT));
                $currents[] = ['op' => 'div', 'args' => [
                    ['op' => 'sub', 'args' => [$voltage, ['op' => 'var', 'name' => 'y'.$index]]],
                    $this->constant($resistor['quantity'], self::OHM),
                ]];
            }
            if ($currents === []) {
                $this->invalid('Isolated dynamic nodes are outside this domain implementation.');
            }
            $derivatives[] = ['op' => 'div', 'args' => [$this->sum($currents), $this->constant($capacitors[$id], self::FARAD)]];
        }
        $conversions = [];
        foreach ($derivatives as $index => &$expression) {
            $this->extractConversions($expression, ['derivatives', $index], $conversions);
        }
        unset($expression);
        foreach (array_values($initial) as $index => $quantity) {
            $conversions[] = $this->conversion($quantity, ['initial', $index]);
        }
        $conversions[] = $this->conversion($start, ['t_start']);
        $conversions[] = $this->conversion($end, ['t_end']);
        if (count($conversions) > 32) {
            $this->invalid('Generated provenance exceeds the kernel conversion limit; reduce the network.');
        }

        return new PreparedScienceModel('ode_ivp', [
            'derivatives' => $derivatives, 'initial' => array_column(array_values($initial), 'si_value'),
            't_start' => $start['si_value'], 't_end' => $end['si_value'], 'tolerance' => $tolerance,
            'dimensions' => ['t' => self::SECOND, 'states' => array_fill(0, count($dynamic), self::VOLT)],
            'outputs' => $selected, 'conversions' => $conversions,
        ], [
            ['name' => 'source_quantity_binding', 'status' => 'checked', 'scope' => 'Declared number/unit pairs occur in their exact quotes, except the listed explicitly unverified interpretations.',
                'interpretations' => array_values(array_map(fn ($quantity) => ['binding' => $quantity['binding'], 'quote' => $quantity['quote']],
                    array_filter([...array_values($initial), $start], fn ($quantity) => $quantity['binding'] !== 'number_unit_occurrence_checked')))],
            ['name' => 'source_quote_presence', 'status' => 'checked', 'scope' => 'Each declared quantity and connection has an exact quote in backend-retained text; quote semantics are not proven.'],
            ['name' => 'declared_topology', 'status' => 'checked', 'scope' => 'Unique endpoints, passive positive components, grounded capacitances and explicit state order.'],
            ['name' => 'kcl_equation_construction', 'status' => 'checked', 'scope' => 'Each resistor contributes (V_neighbor-V_node)/R; each derivative is net incoming current divided by its capacitor.'],
            ['name' => 'declared_initial_conditions', 'status' => 'checked', 'scope' => 'Each dynamic node has an explicit SI-normalized initial voltage.'],
            ['name' => 'text_interpretation_and_completeness', 'status' => 'unverified', 'scope' => 'No deterministic natural-language interpretation or proof of omitted facts.'],
            ['name' => 'physical_idealizations', 'status' => 'assumed', 'scope' => 'Ideal positive constant R/C, constant ideal voltage sources and lumped connections.'],
            ['name' => 'numerical_method_applicability', 'status' => 'unverified', 'scope' => 'This builder does not certify nonstiffness or global numerical error.'],
        ], array_keys($dynamic));
    }

    private function quantity(mixed $raw, array $expectedDimension, string $source, ?string $role, string $path): array
    {
        if (! is_array($raw)) {
            $this->invalid('Quantities require value, unit and a source quote.');
        }
        $this->keys($raw, ['value', 'unit', 'quote']);

        try {
            return $this->quantities->bind($raw, $expectedDimension, $source, $role);
        } catch (ValidationException $exception) {
            $this->invalid($path.': '.$exception->getMessage());
        }
    }

    private function constant(array $quantity, array $dimension): array
    {
        return ['op' => 'const', 'value' => $quantity['si_value'], 'dimension' => $dimension, '_source' => $quantity];
    }

    private function sum(array $terms): array
    {
        if (count($terms) === 1) {
            return $terms[0];
        }
        $middle = intdiv(count($terms), 2);

        return ['op' => 'add', 'args' => [$this->sum(array_slice($terms, 0, $middle)), $this->sum(array_slice($terms, $middle))]];
    }

    private function extractConversions(array &$node, array $path, array &$conversions): void
    {
        if (isset($node['_source'])) {
            $conversions[] = $this->conversion($node['_source'], [...$path, 'value']);
            unset($node['_source']);
        }
        foreach ($node['args'] ?? [] as $index => $child) {
            $this->extractConversions($child, [...$path, 'args', $index], $conversions);
            $node['args'][$index] = $child;
        }
    }

    private function conversion(array $quantity, array $path): array
    {
        return ['path' => $path, 'source_value' => $quantity['source_value'], 'source_unit' => $quantity['source_unit']];
    }

    private function quote(mixed $quote, string $source, string $path): void
    {
        if (! is_string($quote) || trim($quote) === '' || mb_strlen($quote) > 400 || ! str_contains($source, $quote)) {
            $this->invalid($path.': a nonempty exact source quote of at most 400 characters is required.');
        }
    }

    private function identifier(mixed $id): string
    {
        if (! is_string($id) || ! preg_match('/\A[a-zA-Z][a-zA-Z0-9_]{0,23}\z/', $id)) {
            $this->invalid('Identifiers must be bounded ASCII names.');
        }

        return $id;
    }

    private function items(mixed $value, int $minimum, int $maximum): array
    {
        if (! is_array($value) || ! array_is_list($value) || count($value) < $minimum || count($value) > $maximum) {
            $this->invalid('A bounded list is required.');
        }

        return $value;
    }

    private function keys(array $value, array $allowed): void
    {
        if (array_is_list($value) || array_diff(array_keys($value), $allowed) !== []) {
            $this->invalid('Unexpected fields in domain model.');
        }
        $missing = array_diff($allowed, array_keys($value));
        if ($missing !== []) {
            $this->invalid('Missing required domain fields: '.implode(', ', $missing).'. Components require a top-level connection quote in addition to value.quote.');
        }
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['domain_model' => $message]);
    }
}
