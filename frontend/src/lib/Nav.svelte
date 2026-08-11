<script>
	import { page } from '$app/state';
	import { goto } from '$app/navigation';
	import { api, setToken } from '$lib/api.js';
	let menuOpen = $state(false);
	const links = [
		{ href: '/dashboard', label: 'Overview', icon: '⌂' },
		{ href: '/products', label: 'Products', icon: '□' },
		{ href: '/invoices', label: 'Invoices', icon: '▤' },
		{ href: '/barrels', label: 'Barrels', icon: '◉' },
		{ href: '/customers', label: 'Customers', icon: '♙' }
	];
	async function handleLogout() {
		try { await api.logout(); } catch (_) { /* Clear local session regardless. */ }
		setToken(null);
		goto('/login');
	}
</script>

<nav class="nav-shell">
	<a class="brand" href="/dashboard"><span>IM</span><div>Inventory<strong>Manager</strong></div></a>
	<button class="menu-toggle" aria-label="Toggle navigation" onclick={() => menuOpen = !menuOpen}>☰</button>
	<div class:open={menuOpen} class="nav-links">
		{#each links as link}
			<a class:active={page.url.pathname.startsWith(link.href)} href={link.href}><span>{link.icon}</span>{link.label}</a>
		{/each}
	</div>
	<button class="logout" onclick={handleLogout}>Sign out</button>
</nav>

<style>
	.nav-shell { position: sticky; z-index: 20; top: 0; display: flex; height: 66px; align-items: center; padding: 0 max(24px, calc((100vw - 1180px) / 2)); border-bottom: 1px solid #e0e5ed; background: rgb(255 255 255 / 94%); box-shadow: 0 3px 16px rgb(30 52 90 / 4%); backdrop-filter: blur(12px); }
	.brand { display: flex; align-items: center; gap: 10px; margin-right: 42px; color: #1d2941; font-size: 14px; line-height: 1.05; text-decoration: none; }
	.brand > span { display: grid; width: 35px; height: 35px; place-items: center; border-radius: 10px; background: #315ee7; color: #fff; font-size: 12px; font-weight: 850; box-shadow: 0 5px 12px rgb(49 94 231 / 24%); }
	.brand strong { display: block; color: #315ee7; font-size: 12px; }
	.nav-links { display: flex; height: 100%; align-items: center; gap: 4px; }
	.nav-links a { display: flex; height: 38px; align-items: center; gap: 7px; padding: 0 12px; border-radius: 8px; color: #687386; font-size: 12px; font-weight: 650; text-decoration: none; }
	.nav-links a span { color: #8a94a6; font-size: 14px; }
	.nav-links a:hover, .nav-links a.active { background: #eef2ff; color: #315ee7; }
	.logout { margin-left: auto; padding: 8px 12px; border: 1px solid #d8dee8; border-radius: 8px; background: #fff; color: #667085; font-size: 12px; font-weight: 700; cursor: pointer; }
	.menu-toggle { display: none; margin-left: auto; border: 0; background: none; font-size: 22px; }
	@media (max-width: 780px) {
		.nav-shell { padding: 0 16px; }
		.menu-toggle { display: block; }
		.logout { margin-left: 10px; }
		.nav-links { position: absolute; top: 66px; right: 12px; left: 12px; display: none; height: auto; padding: 10px; border: 1px solid #e0e5ed; border-radius: 12px; background: #fff; box-shadow: 0 12px 30px rgb(30 52 90 / 12%); }
		.nav-links.open { display: grid; }
	}
</style>
