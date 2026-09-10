<script>
	import { page } from '$app/state';
	import { goto } from '$app/navigation';

	import { api, setToken } from '$lib/api.js';

	let menuOpen = $state(false);

	const links = [
		{
			href: '/dashboard',
			label: 'Overview',
			icon: '⌂'
		},
		{
			href: '/products',
			label: 'Products',
			icon: '□'
		},
		{
			href: '/invoices',
			label: 'Invoices',
			icon: '▤'
		},
		{
			href: '/barrels',
			label: 'Barrels',
			icon: '◉'
		},
		{
			href: '/customers',
			label: 'Customers',
			icon: '♙'
		},
		{
			href: '/drivers',
			label: 'Drivers',
			icon: '♟'
		},
		{
			href: '/vehicles',
			label: 'Vehicles',
			icon: '▱'
		}
	];

	async function handleLogout() {
		try {
			await api.logout();
		} catch (error) {
			console.error(
				'Logout request failed:',
				error
			);
		}

		setToken(null);

		goto('/login');
	}
</script>


<nav class="nav-shell">

	<!-- =========================
	     BRAND
	========================= -->
	<a
		class="brand"
		href="/dashboard"
	>
		<span class="brand-logo">
			IM
		</span>

		<div class="brand-name">
			Inventory
			<strong>
				Manager
			</strong>
		</div>
	</a>


	<!-- =========================
	     MOBILE MENU BUTTON
	========================= -->
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


	<!-- =========================
	     NAVIGATION LINKS
	========================= -->
	<div
		class="nav-links"
		class:open={menuOpen}
	>

		{#each links as link}

			<a
				href={link.href}
				class:active={
					page.url.pathname.startsWith(
						link.href
					)
				}
				onclick={() => {
					menuOpen = false;
				}}
			>
				<span class="nav-icon">
					{link.icon}
				</span>

				{link.label}
			</a>

		{/each}

	</div>


	<!-- =========================
	     LOGOUT
	========================= -->
	<button
		type="button"
		class="logout"
		onclick={handleLogout}
	>
		Sign out
	</button>

</nav>


<style>
	/* =========================
	   NAVBAR CONTAINER
	========================= */

	.nav-shell {
		position: sticky;
		top: 0;
		z-index: 20;

		display: flex;
		align-items: center;

		height: 66px;

		padding:
			0
			max(
				24px,
				calc((100vw - 1180px) / 2)
			);

		border-bottom: 1px solid #e0e5ed;

		background:
			rgb(255 255 255 / 94%);

		box-shadow:
			0 3px 16px
			rgb(30 52 90 / 4%);

		backdrop-filter: blur(12px);
	}


	/* =========================
	   BRAND
	========================= */

	.brand {
		display: flex;
		align-items: center;

		gap: 10px;

		margin-right: 28px;

		color: #1d2941;

		font-size: 14px;
		line-height: 1.05;

		text-decoration: none;
	}

	.brand-logo {
		display: grid;

		width: 35px;
		height: 35px;

		place-items: center;

		border-radius: 10px;

		background: #315ee7;
		color: white;

		font-size: 12px;
		font-weight: 850;

		box-shadow:
			0 5px 12px
			rgb(49 94 231 / 24%);
	}

	.brand-name strong {
		display: block;

		color: #315ee7;

		font-size: 12px;
	}


	/* =========================
	   NAVIGATION LINKS
	========================= */

	.nav-links {
		display: flex;
		align-items: center;

		height: 100%;

		gap: 2px;
	}

	.nav-links a {
		display: flex;
		align-items: center;

		height: 38px;

		gap: 7px;

		padding:
			0
			10px;

		border-radius: 8px;

		color: #687386;

		font-size: 12px;
		font-weight: 650;

		text-decoration: none;

		transition:
			background 0.15s ease,
			color 0.15s ease;
	}

	.nav-icon {
		color: #8a94a6;

		font-size: 14px;
	}

	.nav-links a:hover,
	.nav-links a.active {
		background: #eef2ff;
		color: #315ee7;
	}

	.nav-links a:hover .nav-icon,
	.nav-links a.active .nav-icon {
		color: #315ee7;
	}


	/* =========================
	   SIGN OUT
	========================= */

	.logout {
		margin-left: auto;

		padding:
			8px
			12px;

		border: 1px solid #d8dee8;
		border-radius: 8px;

		background: white;
		color: #667085;

		font-size: 12px;
		font-weight: 700;

		cursor: pointer;

		transition:
			background 0.15s ease,
			border-color 0.15s ease,
			color 0.15s ease;
	}

	.logout:hover {
		border-color: #c7ced9;

		background: #f8fafc;
		color: #344054;
	}


	/* =========================
	   MOBILE MENU BUTTON
	========================= */

	.menu-toggle {
		display: none;

		margin-left: auto;

		border: none;

		background: none;
		color: #344054;

		font-size: 22px;

		cursor: pointer;
	}


	/* =========================
	   RESPONSIVE
	========================= */

	@media (max-width: 1000px) {

		.brand {
			margin-right: 18px;
		}

		.nav-links a {
			padding:
				0
				8px;

			font-size: 11px;
		}

		.nav-links {
			gap: 0;
		}
	}


	@media (max-width: 780px) {

		.nav-shell {
			padding:
				0
				16px;
		}

		.brand {
			margin-right: 0;
		}


		/* Show mobile menu button */
		.menu-toggle {
			display: block;
		}


		/* Hide menu by default */
		.nav-links {
			position: absolute;

			top: 66px;
			right: 12px;
			left: 12px;

			display: none;

			height: auto;

			padding: 10px;

			border: 1px solid #e0e5ed;
			border-radius: 12px;

			background: white;

			box-shadow:
				0 12px 30px
				rgb(30 52 90 / 12%);
		}


		/* Open mobile menu */
		.nav-links.open {
			display: grid;
		}


		.nav-links a {
			width: 100%;
			height: 42px;

			box-sizing: border-box;

			padding:
				0
				12px;

			font-size: 13px;
		}


		.logout {
			margin-left: 10px;
		}
	}
</style>