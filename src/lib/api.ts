type ReqOptions = {
  method?: string;
  body?: any;
  headers?: Record<string, string>;
  signal?: AbortSignal;
};

let _csrfFetched = false;

async function ensureCsrf(signal?: AbortSignal) {
  if (_csrfFetched) return;
  try {
    await fetch('/sanctum/csrf-cookie', { credentials: 'include', signal });
    _csrfFetched = true;
  } catch (e) {
    // ignore
  }
}

export async function request(path: string, opts: ReqOptions = {}) {
  const method = (opts.method || 'GET').toUpperCase();
  const headers: Record<string, string> = { Accept: 'application/json', ...(opts.headers || {}) };

  if (method !== 'GET') {
    await ensureCsrf(opts.signal);
    // read XSRF-TOKEN cookie and set header for fetch (Laravel expects X-XSRF-TOKEN)
    try {
      const getCookie = (name: string) => {
        const v = document.cookie.match('(^|;)\\s*' + name + '\\s*=\\s*([^;]+)');
        return v ? v.pop() : null;
      };
      const xsrf = getCookie('XSRF-TOKEN');
      if (xsrf) headers['X-XSRF-TOKEN'] = decodeURIComponent(xsrf);
    } catch (e) {
      // if document isn't available (SSR/testing), ignore
    }
  }

  // attach token from localStorage when available (for token-based auth)
  try {
    if (typeof window !== 'undefined') {
      const token = localStorage.getItem('authToken');
      if (token && !headers['Authorization']) {
        headers['Authorization'] = `Bearer ${token}`;
      }
    }
  } catch (e) {
    // ignore in SSR or restricted environments
  }

  let body: any = undefined;
  if (opts.body instanceof FormData) {
    body = opts.body;
    // let fetch set FormData headers
  } else if (opts.body !== undefined) {
    headers['Content-Type'] = 'application/json';
    body = JSON.stringify(opts.body);
  }

  const res = await fetch(path, { method, headers, body, credentials: 'include', signal: opts.signal });
  const ct = res.headers.get('content-type') || '';
  if (ct.includes('application/json')) {
    let data:any;
    try { data = await res.json(); }
    catch {
      const error=new Error('The service returned an unreadable response. Please try again.') as Error & {status?:number};
      error.status=res.status;throw error;
    }
    if (!res.ok) {
      const error = new Error(res.status >= 500 ? 'The service is temporarily unavailable. Please try again.' : data?.message || `Request failed: ${res.status}`) as Error & { status?: number; errors?: Record<string, string[]> };
      error.status = res.status;
      if(res.status < 500) error.errors = data?.errors;
      throw error;
    }
    return data;
  }

  if(res.ok && res.status!==204 && path.startsWith('/api/')){
    const error=new Error('The service returned an unexpected response. Please refresh and try again.') as Error & {status?:number};
    error.status=res.status;throw error;
  }
  const text = await res.text();
  if (!res.ok) {
    const error = new Error(`Request failed: ${res.status}`) as Error & { status?: number };
    error.status = res.status;
    throw error;
  }
  return text;
}

export default { request };
