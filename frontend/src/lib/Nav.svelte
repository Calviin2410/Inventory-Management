<script>
	import { page } from "$app/state";
	import { goto } from "$app/navigation";

	import { api, setToken } from "$lib/api.js";
	import { user } from "$lib/stores/auth.js";

	let menuOpen = $state(false);

	// 导航项目
	const links = [
		{
			href: "/dashboard",
			label: "Overview",
		},
		// {
		// 	href: "/products",
		// 	label: "Products",
		// },
		{
			href: "/invoices",
			label: "Invoices",
		},
		{
			href: "/barrels",
			label: "Barrels",
		},
		{
			href: "/customers",
			label: "Customers",
		},
		{
			href: "/drivers",
			label: "Drivers",
			adminOnly: true,
		},
		{
			href: "/vehicles",
			label: "Vehicles",
			adminOnly: true,
		},
		{
			href: "/reports",
			label: "Reports",
			adminOnly: true,
		},
	];

	let visibleLinks = $derived(
		links.filter((link) => !link.adminOnly || $user?.role === "admin"),
	);

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

<nav class="nav-shell">
	<!-- 品牌 -->
	<a class="brand" href="/dashboard">
		<span class="brand-logo"> IM </span>

		<div class="brand-name">
			Inventory

			<strong> Manager </strong>
		</div>
	</a>

	<!-- 手机菜单按钮 -->
	<button
		type="button"
		class="menu-toggle"
		aria-label="Toggle navigation"
		onclick={() => {
			menuOpen = !menuOpen;
		}}
	>
		☰
	</button>

	<!-- 导航 -->
	<div class="nav-links" class:open={menuOpen}>
		{#each visibleLinks as link}
			<a
				href={link.href}
				class:active={page.url.pathname.startsWith(link.href)}
				onclick={() => {
					menuOpen = false;
				}}
			>
				{link.label}
			</a>
		{/each}
	</div>

	<!-- 登出 -->
	<button type="button" class="logout" onclick={handleLogout}>
		Sign out
	</button>
</nav>

<style>
	/* 导航栏 */
	.nav-shell {
		position: sticky;
		top: 0;
		z-index: 50;

		display: flex;
		align-items: center;

		height: 68px;

		padding: 0 max(24px, calc((100vw - 1180px) / 2));

		border-bottom: 1px solid #e2e8f0;

		background: rgb(255 255 255 / 92%);

		box-shadow:
			0 1px 2px rgb(15 23 42 / 3%),
			0 6px 20px rgb(15 23 42 / 4%);

		backdrop-filter: blur(14px);
	}

	.brand {
		display: flex;
		align-items: center;

		gap: 10px;

		margin-right: 30px;

		color: #0f172a;

		font-size: 14px;
		font-weight: 500;

		line-height: 1.05;

		text-decoration: none;
	}

	.brand-logo {
		display: grid;
		place-items: center;

		width: 36px;
		height: 36px;

		border-radius: 10px;

		background: linear-gradient(180deg, #3b82f6, #2563eb);

		color: white;

		font-size: 12px;
		font-weight: 800;

		box-shadow: 0 6px 16px rgb(37 99 235 / 22%);
	}

	.brand-name {
		color: #334155;
	}

	.brand-name strong {
		display: block;

		margin-top: 2px;

		color: #2563eb;

		font-size: 12px;
		font-weight: 700;
	}

	.nav-links {
		display: flex;
		align-items: center;

		height: 100%;

		gap: 3px;
	}

	.nav-links a {
		display: flex;
		align-items: center;

		height: 38px;

		padding: 0 11px;

		border-radius: 9px;

		color: #64748b;

		font-size: 12px;
		font-weight: 650;

		text-decoration: none;

		transition:
			background 0.15s ease,
			color 0.15s ease,
			transform 0.15s ease;
	}

	.nav-links a:hover {
		background: #f1f5f9;
		color: #334155;
	}

	.nav-links a.active {
		background: #eef2ff;
		color: #2563eb;

		box-shadow: inset 0 0 0 1px rgb(37 99 235 / 6%);
	}

	.nav-links a:active {
		transform: translateY(1px);
	}

	.logout {
		margin-left: auto;

		height: 38px;

		padding: 0 14px;

		border: 1px solid #dbe2ea;
		border-radius: 9px;

		background: #fff;

		color: #64748b;

		font-size: 12px;
		font-weight: 700;

		cursor: pointer;

		transition:
			background 0.15s ease,
			border-color 0.15s ease,
			color 0.15s ease,
			box-shadow 0.15s ease;
	}

	.logout:hover {
		border-color: #cbd5e1;

		background: #f8fafc;

		color: #334155;

		box-shadow: 0 2px 8px rgb(15 23 42 / 5%);
	}

	.menu-toggle {
		display: none;

		margin-left: auto;

		width: 38px;
		height: 38px;

		border: 1px solid #e2e8f0;
		border-radius: 8px;

		background: #fff;

		color: #334155;

		font-size: 20px;

		cursor: pointer;
	}

	@media (max-width: 1000px) {
		.brand {
			margin-right: 18px;
		}

		.nav-links a {
			padding: 0 8px;

			font-size: 11px;
		}

		.nav-links {
			gap: 0;
		}
	}

	@media (max-width: 780px) {
		.nav-shell {
			padding: 0 16px;
		}

		.brand {
			margin-right: 0;
		}

		.menu-toggle {
			display: grid;
			place-items: center;
		}

		.nav-links {
			position: absolute;

			top: 68px;
			right: 12px;
			left: 12px;

			display: none;

			height: auto;

			padding: 10px;

			border: 1px solid #e2e8f0;
			border-radius: 12px;

			background: white;

			box-shadow: 0 18px 40px rgb(15 23 42 / 12%);
		}

		.nav-links.open {
			display: grid;
		}

		.nav-links a {
			width: 100%;
			height: 42px;

			box-sizing: border-box;

			padding: 0 12px;

			font-size: 13px;
		}

		.logout {
			margin-left: 10px;
		}
	}
</style>
