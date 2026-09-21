import { finite, invalid, list, object, tolerance } from './contract.js';

function dimension(value) {
    return list(value, 7).map(exponent => {
        finite(exponent);
        if (Math.abs(exponent) > 32) invalid('Dimension exponent limit exceeded.');
        return exponent;
    });
}

function expressionProjector(variables = ['x', 't', 'y0', 'y1', 'y2', 'y3']) {
    let nodes = 0;
    const project = (value, depth = 0) => {
        const node = object(value);
        if (depth > 12 || ++nodes > 128) invalid('Expression resource limit exceeded.');
        if (node.op === 'const') return { op: 'const', value: finite(node.value),
            ...(node.dimension === undefined ? {} : { dimension: dimension(node.dimension) }) };
        if (node.op === 'var') {
            if (typeof node.name !== 'string' || !variables.includes(node.name)) invalid('Unknown variable.');
            return { op: 'var', name: node.name };
        }
        const arity = ['add', 'sub', 'mul', 'div', 'pow'].includes(node.op) ? 2 : ['neg', 'sin', 'cos', 'exp', 'log', 'sqrt'].includes(node.op) ? 1 : 0;
        if (!arity) invalid('Unknown expression operator.');
        return { op: node.op, args: list(node.args, arity).map(child => project(child, depth + 1)) };
    };
    return project;
}

/** Project bounded fields before structured cloning, discarding arbitrary metadata. */
export function prepareScienceInput(solver, value) {
    const input = object(value);
    let result;
    if (solver === 'linear_system') {
        const n = list(input.matrix, 1, 32).length;
        result = { matrix: input.matrix.map(row => list(row, n).map(finite)), rhs: list(input.rhs, n).map(finite) };
        if (input.dimensions !== undefined) {
            const d = object(input.dimensions);
            result.dimensions = { variables: list(d.variables, n).map(dimension), rhs: list(d.rhs, n).map(dimension),
                matrix: list(d.matrix, n).map(row => list(row, n).map(dimension)) };
        }
    } else if (solver === 'integrate' || solver === 'root_scalar') {
        result = { expression: expressionProjector()(input.expression), lower: finite(input.lower), upper: finite(input.upper),
            tolerance: tolerance(input.tolerance, solver === 'root_scalar' ? 1e-8 : 1e-7, solver === 'root_scalar' ? 1e-12 : 1e-10) };
        if (input.dimensions !== undefined) {
            const d = object(input.dimensions);
            result.dimensions = { x: dimension(d.x), output: dimension(d.output) };
        }
    } else if (solver === 'ode_ivp') {
        const initial = list(input.initial, 1, 4).map(finite);
        result = { initial, derivatives: list(input.derivatives, initial.length).map(expression => expressionProjector()(expression)),
            t_start: finite(input.t_start), t_end: finite(input.t_end), tolerance: tolerance(input.tolerance, 1e-6, 1e-8) };
        if (input.dimensions !== undefined) {
            const d = object(input.dimensions);
            result.dimensions = { t: dimension(d.t), states: list(d.states, initial.length).map(dimension) };
        }
    } else invalid('Solver is unavailable.');
    if (input.outputs !== undefined) {
        const names = new Set();
        result.outputs = list(input.outputs, 1, 8).map(value => {
            const output = object(value);
            if (typeof output.name !== 'string' || !/^[a-zA-Z][a-zA-Z0-9_]{0,63}$/.test(output.name)
                || names.has(output.name)) invalid('Invalid output name.');
            names.add(output.name);
            return { name: output.name, dimension: dimension(output.dimension),
                expression: expressionProjector(Array.from({ length: 32 }, (_, i) => `r${i}`))(output.expression) };
        });
    }
    if (input.conversions !== undefined) {
        result.conversions = list(input.conversions, 0, 32).map(value => {
            const conversion = object(value);
            const path = list(conversion.path, 1, 32).map(segment => {
                if (typeof segment === 'string' && segment.length >= 1 && segment.length <= 32) return segment;
                if (Number.isInteger(segment) && segment >= 0 && segment <= 127) return segment;
                invalid('Malformed conversion path.');
            });
            if (typeof conversion.source_unit !== 'string' || conversion.source_unit.length < 1 || conversion.source_unit.length > 16) {
                invalid('Malformed conversion provenance.');
            }
            return { path, source_value: finite(conversion.source_value), source_unit: conversion.source_unit };
        });
    }
    return result;
}
