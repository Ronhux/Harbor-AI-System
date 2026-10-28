import { request } from './api';
/** Clear authentication data without deleting appearance and language preferences. */
export function clearAuthStorage() {
  for (const key of ['authToken', 'userRole', 'userName', 'userId', 'user', 'producerId', 'buyerId', 'userEmail', 'verificationStatus', 'producerType']) { try { localStorage.removeItem(key); } catch { /* Storage may be blocked; server logout already revoked access. */ } }
  try { sessionStorage.removeItem('harbor:notification'); } catch { /* Restricted browser storage. */ }
}

export async function logoutSession() {
  try { await request('/api/logout', { method: 'POST' }); }
  catch (error) { if ((error as { status?: number }).status !== 401) throw error; }
  clearAuthStorage();
}
