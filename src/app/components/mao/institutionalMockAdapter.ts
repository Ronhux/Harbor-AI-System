import { normalizePreparedFailure, preparedReplayNotice } from '../../contracts/preparedFailures';
import type { FieldErrors } from './institutionalDraftModel';

export type MockScenario = 'local_valid' | 'validation' | 'unauthorized' | 'forbidden' | 'missing' | 'stale' | 'quantity' | 'duplicate_conflict' | 'duplicate_replay' | 'unavailable' | 'network' | 'server';
export type MockOutcome =
  | { kind: 'local'; message: string }
  | { kind: 'http_fixture'; status: number; body: { message: string; errors?: FieldErrors } }
  | { kind: 'network'; message: string };
/** Local fixtures only; no URL, transport, persistence or fabricated success-model schema. */
export async function mockPostingCheck(scenario: MockScenario): Promise<MockOutcome> {
  await new Promise(resolve => setTimeout(resolve, 200));
  if (scenario === 'local_valid') return { kind: 'local', message: 'Local draft checks passed. No server validation, authorization or persistence occurred.' };
  if (scenario === 'duplicate_replay') return { kind: 'local', message: 'Replay example: '+preparedReplayNotice };
  if (scenario === 'network') return { kind: 'network', message: 'Simulated network failure. Local draft retained; no request was sent.' };
  const fixtures: Record<Exclude<MockScenario, 'local_valid' | 'duplicate_replay' | 'network'>, { status: number; message: string; errors?: FieldErrors }> = {
    validation: { status: 422, message: 'The given data was invalid.', errors: { 'items.0.quantity': ['Synthetic field validation error.'] } },
    unauthorized: { status: 401, message: 'Unauthenticated.' },
    forbidden: { status: 403, message: 'Synthetic actor lacks institutional posting authorization.' },
    missing: { status: 404, message: 'Synthetic record or disabled workflow is unavailable.' },
    stale: { status: 409, message: 'Synthetic revision conflict. Reload current state before preparing a new decision.' },
    quantity: { status: 409, message: 'Remaining quantity exceeded' },
    duplicate_conflict: { status: 409, message: 'Synthetic operation key was reused with different content.' },
    unavailable: { status: 503, message: 'Official posting rules and notices require separate approval' },
    server: { status: 500, message: 'Simulated server failure. No operation was executed.' },
  };
  const fixture = fixtures[scenario];
  if (!fixture) return { kind: 'http_fixture', status: 500, body: { message: 'Unsupported synthetic response scenario. No operation was executed.' } };
  const { status, ...body } = fixture;
  const normalized=normalizePreparedFailure(status,body,['items.0.quantity']);
  return { kind: 'http_fixture', status, body:{message:normalized.message,...(body.errors?{errors:normalized.fields}:{})} };
}
