type ReqOptions = {
  method?: string;
  body?: any;
  headers?: Record<string, string>;
};

let _csrfFetched = false;

async function ensureCsrf() {
  if (_csrfFetched) return;
  try {
    await fetch('/sanctum/csrf-cookie', { credentials: 'include' });
    _csrfFetched = true;
  } catch (e) {
    // ignore
  }
}

export async function request(path: string, opts: ReqOptions = {}) {
  const method = (opts.method || 'GET').toUpperCase();
  const headers: Record<string, string> = { Accept: 'application/json', ...(opts.headers || {}) };

  if (method !== 'GET') {
    await ensureCsrf();
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

  const res = await fetch(path, { method, headers, body, credentials: 'include' });
  const ct = res.headers.get('content-type') || '';
  if (ct.includes('application/json')) {
    const data = await res.json();
    if (!res.ok) throw new Error(data?.message || JSON.stringify(data));
    return data;
  }

  const text = await res.text();
  if (!res.ok) throw new Error(text || `Request failed: ${res.status}`);
  return text;
}

export default { request };
