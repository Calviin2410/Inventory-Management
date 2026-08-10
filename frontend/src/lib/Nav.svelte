<script>
	import { goto } from '$app/navigation';
	import { api, setToken } from '$lib/api.js';

	async function handleLogout() {
		try {
			await api.logout();
		} catch (e) {
			// ignore — token might already be invalid, still clear it locally
		}
		setToken(null);
		goto('/login');
	}
</script>

<nav>
	<div class="links">
		<a href="/dashboard">首页</a>
		<a href="/products">商品</a>
		<a href="/invoices">发票</a>
		<a href="/barrels">桶状态</a>
		<a href="/customers">Customer</a>
	</div>
	<button on:click={handleLogout}>登出</button>
</nav>

<style>
	nav {
		display: flex;
		justify-content: space-between;
		align-items: center;
		padding: 0.75rem 1.5rem;
		background: #1e293b;
		font-family: sans-serif;
	}
	.links {
		display: flex;
		gap: 1.25rem;
	}
	.links a {
		color: #e2e8f0;
		text-decoration: none;
		font-size: 0.95rem;
	}
	.links a:hover {
		color: white;
	}
	button {
		background: transparent;
		border: 1px solid #475569;
		color: #e2e8f0;
		padding: 0.35rem 0.8rem;
		border-radius: 4px;
		cursor: pointer;
		font-size: 0.85rem;
	}
</style>
