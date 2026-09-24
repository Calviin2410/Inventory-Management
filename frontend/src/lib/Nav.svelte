<script>
	import { page } from "$app/state";
	import { goto } from "$app/navigation";
	import { api, setToken } from "$lib/api.js";
	import { user } from "$lib/stores/auth.js";

	let menuOpen = $state(false);
	const links = [
		{ href: "/dashboard", label: "Dashboard", icon: "▦" },
		{ href: "/invoices", label: "Invoices", icon: "▤" },
		{ href: "/barrels", label: "Barrel inventory", icon: "◇" },
		{ href: "/customers", label: "Customers", icon: "♙" },
		{ href: "/drivers", label: "Drivers", icon: "◉", adminOnly: true },
		{ href: "/vehicles", label: "Vehicles", icon: "▰", adminOnly: true },
		{ href: "/staff", label: "Staff", icon: "♟", adminOnly: true },
		{ href: "/settlement", label: "Settlement", icon: "✓", adminOnly: true },
		{ href: "/activity-logs", label: "Activity log", icon: "≡", adminOnly: true },
		{ href: "/reports", label: "Reports", icon: "⌁", adminOnly: true },
	];
	let visibleLinks = $derived(
		links.filter((link) => !link.adminOnly || $user?.role === "admin"),
	);
	let initials = $derived(
		($user?.name || $user?.email || "User").slice(0, 2).toUpperCase(),
	);
	const routeNames = {
		dashboard: "Dashboard",
		invoices: "Invoices",
		barrels: "Barrel inventory",
		customers: "Customers",
		drivers: "Drivers",
		vehicles: "Vehicles",
		staff: "Staff",
		settlement: "Settlement",
		"activity-logs": "Activity log",
		reports: "Reports",
		products: "Products",
		create: "Create new",
		edit: "Edit",
	};
	let breadcrumbs = $derived.by(() => {
		const parts = page.url.pathname.split("/").filter(Boolean);
		if (parts.length === 0)
			return [{ label: "Dashboard", href: "/dashboard" }];
		return parts
			.filter((part) => !/^\d+$/.test(part))
			.map((part, index, filtered) => ({
				label:
					routeNames[part] ||
					part
						.replace(/-/g, " ")
						.replace(/^./, (letter) => letter.toUpperCase()),
				href: "/" + filtered.slice(0, index + 1).join("/"),
			}));
	});

	async function handleLogout() {
		try {
			await api.logout();
		} catch (error) {
			console.error("Logout request failed:", error);
		}
		setToken(null);
		user.set(null);
		goto("/login");
	}
</script>

<button
	class="mobile-trigger"
	type="button"
	aria-label="Open navigation"
	onclick={() => (menuOpen = true)}
>
	<span></span><span></span><span></span>
</button>
{#if menuOpen}
	<button
		class="nav-backdrop"
		aria-label="Close navigation"
		onclick={() => (menuOpen = false)}
	></button>
{/if}

<header class="topbar">
	<nav class="breadcrumbs" aria-label="Breadcrumb">
		<a class="home-crumb" href="/dashboard" aria-label="Dashboard">⌂</a>
		{#each breadcrumbs as crumb, index}
			<span class="separator">/</span>
			{#if index < breadcrumbs.length - 1}
				<a href={crumb.href}>{crumb.label}</a>
			{:else}
				<span class="current" aria-current="page">{crumb.label}</span>
			{/if}
		{/each}
	</nav>
</header>

<aside class="sidebar" class:open={menuOpen}>
	<div class="brand-row">
		<a class="brand" href="/dashboard" onclick={() => (menuOpen = false)}>
			<img class="brand-logo" src="/images/tks-brand-logo.png" alt="TKS Waste Management" />
		</a>
		<button
			class="mobile-close"
			type="button"
			aria-label="Close navigation"
			onclick={() => (menuOpen = false)}>×</button
		>
	</div>
	<nav class="nav-links" aria-label="Main navigation">
		<p class="nav-label">MAIN MENU</p>
		{#each visibleLinks as link}
			<a
				href={link.href}
				class:active={page.url.pathname.startsWith(link.href)}
				onclick={() => (menuOpen = false)}
			>
				<span class="nav-icon" aria-hidden="true">{link.icon}</span
				><span>{link.label}</span>
			</a>
		{/each}
	</nav>
	<div class="sidebar-footer">
		<div class="account">
			<div class="avatar">{initials}</div>
			<div class="account-copy">
				<strong>{$user?.name || "Account"}</strong>
				<small
					>{$user?.role === "admin"
						? "Administrator"
						: "Staff member"}</small
				>
			</div>
			<button
				class="sign-out"
				type="button"
				title="Sign out"
				aria-label="Sign out"
				onclick={handleLogout}
			>
				<span aria-hidden="true">↪</span>
				Sign out
			</button>
		</div>
	</div>
</aside>

<style>
	.sidebar {
		position: fixed;
		inset: 0 auto 0 0;
		z-index: 60;
		display: flex;
		width: 248px;
		box-sizing: border-box;
		flex-direction: column;
		padding: 22px 16px 16px;
		border-right: 1px solid #e3e8ef;
		background: #fff;
		box-shadow: 8px 0 30px rgb(15 23 42 / 2%);
	}
	.brand-row {
		display: flex;
		align-items: center;
		justify-content: space-between;
		padding: 0 7px;
	}
	.brand {
		display: block;
		color: #172033;
		text-decoration: none;
	}
	.brand-logo {
		display: block;
		width: 196px;
		height: 72px;
		object-fit: contain;
		object-position: left center;
	}
	.nav-links {
		display: flex;
		flex: 1;
		flex-direction: column;
		gap: 4px;
	}
	.nav-label {
		margin: 30px 12px 7px;
		color: #202938;
		font-size: 11px;
		font-weight: 800;
		letter-spacing: 0.14em;
	}
	.nav-links a {
		display: flex;
		align-items: center;
		gap: 12px;
		min-height: 42px;
		padding: 0 12px;
		border-radius: 9px;
		color: #202938;
		font-size: 13px;
		font-weight: 650;
		text-decoration: none;
		transition: 0.16s ease;
	}
	.nav-links a:hover {
		background: #f5f7fb;
		color: #0f172a;
	}
	.nav-links a.active {
		background: #edf2ff;
		color: #2049b6;
		box-shadow: inset 3px 0 #2d5bd1;
	}
	.nav-icon {
		width: 18px;
		color: #526074;
		text-align: center;
		font-size: 17px;
	}
	.nav-links a.active .nav-icon {
		color: #2b58ca;
	}
	.sidebar-footer {
		border-top: 1px solid #edf0f4;
		padding-top: 14px;
	}
	.account {
		display: flex;
		align-items: center;
		gap: 10px;
		padding: 10px 8px;
		border-radius: 10px;
		background: #f8fafc;
	}
	.avatar {
		display: grid;
		width: 32px;
		height: 32px;
		place-items: center;
		flex: none;
		border-radius: 50%;
		background: #dce7ff;
		color: #244cad;
		font-size: 10px;
		font-weight: 800;
	}
	.account-copy {
		min-width: 0;
		flex: 1;
	}
	.account-copy strong,
	.account-copy small {
		display: block;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}
	.account-copy strong {
		color: #293449;
		font-size: 11px;
	}
	.account-copy small {
		margin-top: 2px;
		color: #939cac;
		font-size: 9px;
	}
	.account .sign-out {
		display: inline-flex;
		align-items: center;
		gap: 4px;
		flex: none;
		padding: 6px 7px;
		border: 0;
		border-radius: 6px;
		background: transparent;
		color: #202938;
		cursor: pointer;
		font-size: 9px;
		font-weight: 750;
	}
	.account .sign-out span {
		font-size: 14px;
	}
	.account .sign-out:hover {
		background: #fff0f0;
		color: #b43d3d;
	}
	.mobile-trigger,
	.mobile-close,
	.nav-backdrop {
		display: none;
	}
	.topbar {
		position: fixed;
		inset: 0 0 auto 248px;
		z-index: 45;
		display: flex;
		height: 64px;
		align-items: center;
		justify-content: space-between;
		padding: 0 42px;
		border-bottom: 1px solid #e3e8ef;
		background: rgb(255 255 255 / 94%);
		backdrop-filter: blur(12px);
	}
	.breadcrumbs {
		display: flex;
		align-items: center;
		gap: 9px;
		min-width: 0;
		color: #9aa4b3;
		font-size: 11px;
		font-weight: 600;
	}
	.breadcrumbs a {
		color: #7c8799;
		text-decoration: none;
		transition: color 0.15s ease;
	}
	.breadcrumbs a:hover {
		color: #2855bd;
	}
	.home-crumb {
		font-size: 15px;
	}
	.separator {
		color: #c5cbd4;
	}
	.current {
		overflow: hidden;
		color: #273349;
		font-weight: 700;
		text-overflow: ellipsis;
		white-space: nowrap;
	}
	@media (max-width: 820px) {
		.sidebar {
			transform: translateX(-105%);
			transition: transform 0.22s ease;
		}
		.sidebar.open {
			transform: translateX(0);
		}
		.mobile-trigger {
			position: fixed;
			top: 15px;
			left: 16px;
			z-index: 55;
			display: grid;
			width: 40px;
			height: 40px;
			place-content: center;
			gap: 4px;
			border: 1px solid #dfe5ed;
			border-radius: 9px;
			background: #fff;
			box-shadow: 0 4px 14px rgb(15 23 42 / 8%);
		}
		.mobile-trigger span {
			width: 17px;
			height: 2px;
			border-radius: 3px;
			background: #475569;
		}
		.mobile-close {
			display: block;
			border: 0;
			background: transparent;
			color: #64748b;
			font-size: 25px;
			cursor: pointer;
		}
		.nav-backdrop {
			position: fixed;
			inset: 0;
			z-index: 59;
			display: block;
			border: 0;
			background: rgb(15 23 42 / 38%);
			backdrop-filter: blur(2px);
		}
		.topbar {
			left: 0;
			height: 70px;
			padding: 0 16px 0 68px;
		}
	}
	@media (max-width: 480px) {
		.breadcrumbs {
			gap: 6px;
			font-size: 10px;
		}
	}
</style>
