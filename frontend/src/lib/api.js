// 简单的 fetch 封装,自动带上 Authorization header
// 后端地址按需修改,建议放进 .env 里用 import.meta.env.VITE_API_BASE_URL
const API_BASE_URL = 'http://localhost:8000/api';

function getToken() {
	if (typeof localStorage === 'undefined') return null;
	return localStorage.getItem('token');
}

export function setToken(token) {
	if (typeof localStorage === 'undefined') return;
	if (token) {
		localStorage.setItem('token', token);
	} else {
		localStorage.removeItem('token');
	}
}

async function request(path, { method = 'GET', body, headers = {} } = {}) {
	const token = getToken();

	const res = await fetch(`${API_BASE_URL}${path}`, {
		method,
		headers: {
			'Content-Type': 'application/json',
			Accept: 'application/json',
			...(token ? { Authorization: `Bearer ${token}` } : {}),
			...headers
		},
		body: body ? JSON.stringify(body) : undefined
	});

	const data = await res.json().catch(() => null);

	if (!res.ok) {
		const error = new Error(
			data?.message || `Request Failed (${res.status})`
		);

		error.status = res.status;
		error.data = data;
		error.errors = data?.errors ?? null;

		throw error;
	}

	return data;
}

export const api = {
	// 认证
	login: (email, password) => request('/login', { method: 'POST', body: { email, password } }),
	register: (name, email, password) =>
		request('/register', { method: 'POST', body: { name, email, password } }),
	logout: () => request('/logout', { method: 'POST' }),
	me: () => request('/me'),

	getDashboardSummary: () =>
		request('/dashboard-summary'),

	// 商品
	getProducts: (params = {}) => {
		const query = new URLSearchParams(params).toString();
		return request(`/products${query ? `?${query}` : ''}`);
	},
	getProduct: (id) => request(`/products/${id}`),
	createProduct: (payload) => request('/products', { method: 'POST', body: payload }),
	updateProduct: (id, payload) => request(`/products/${id}`, { method: 'PUT', body: payload }),
	deleteProduct: (id) => request(`/products/${id}`, { method: 'DELETE' }),

	// Stock movements (in/out/adjustment)
	createStockMovement: (productId, payload) =>
		request(`/products/${productId}/stock-movements`, { method: 'POST', body: payload }),

	// Customers
	getCustomers: (params = {}) => {
		const query = new URLSearchParams(params).toString();
		return request(`/customers${query ? `?${query}` : ''}`);
	},
	createCustomer: (payload) => request('/customers', { method: 'POST', body: payload }),

	getCustomerOptions: () => request('/customers/options'),

	// Barrels
	getBarrels: (params = {}) => {
		const query = new URLSearchParams(params).toString();
		return request(`/barrels${query ? `?${query}` : ''}`);
	},
	getAvailableBarrels: () => request('/barrels/available'),
	createBarrel: (payload) => request('/barrels', { method: 'POST', body: payload }),
	updateBarrel: (id, payload) => request(`/barrels/${id}`, { method: 'PATCH', body: payload }),
	returnBarrel: (id) => request(`/barrels/${id}/return`, { method: 'POST' }),
	deleteBarrel: (id) => request(`/barrels/${id}`, { method: 'DELETE' }),
	// Invoices
	getNextInvoiceNo: () => request('/invoices-next-number'),
	getInvoices: (params = {}) => {
		const query = new URLSearchParams(params).toString();
		return request(`/invoices${query ? `?${query}` : ''}`);
	},
	getInvoice: (id) => request(`/invoices/${id}`),
	createInvoice: (payload) => request('/invoices', { method: 'POST', body: payload }),
	updateInvoice: (id, payload) => request(`/invoices/${id}`, { method: 'PATCH', body: payload }),

	getDrivers: () => request('/drivers'),
	createDriver: (payload) => request('/drivers', { method: 'POST', body: payload }),

	getVehicles: () => request('/vehicles'),
	getVehicle: (id) => request(`/vehicles/${id}`),
	createVehicle: (payload) => request('/vehicles', { method: 'POST', body: payload }),
	updateVehicle: (id, data) => request(`/vehicles/${id}`, { method: "PUT", body: data }),
	deleteVehicle: (id) => request(`/vehicles/${id}`, { method: "DELETE" }),
	getRentalReport: (params = {}) => { const query = new URLSearchParams(params).toString(); return request(`/reports/rentals${query ? `?${query}` : ''}`); },
};
