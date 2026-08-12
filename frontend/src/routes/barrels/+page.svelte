<script>
	import { onMount } from 'svelte';
	import { api } from '$lib/api.js';
	import Nav from '$lib/Nav.svelte';

	// =========================
	// State
	// =========================
	let barrels = $state([]);
	let loading = $state(true);
	let errorMessage = $state('');

	let status = $state('');
	let searchCode = $state('');
	let view = $state('table');

	let openMenuId = $state(null);


	// =========================
	// Load Barrels
	// =========================
	async function loadBarrels() {
		loading = true;
		errorMessage = '';

		try {
			const params = status
				? { status }
				: {};

			barrels = await api.getBarrels(params);
		} catch (error) {
			errorMessage =
				error?.message || 'Unable to load barrels';
		} finally {
			loading = false;
		}
	}


	// =========================
	// Update Status
	// =========================
	async function handleStatusUpdate(barrel, newStatus) {
		try {
			/*
				之后如果 backend 已经有 update API，
				这里可以改成：

				await api.updateBarrel(barrel.id, {
					status: newStatus
				});
			*/

			barrel.status = newStatus;
			openMenuId = null;

		} catch (error) {
			errorMessage =
				error?.message || 'Unable to update barrel status';
		}
	}


	// =========================
	// Search Filter
	// =========================
	let filtered = $derived(
		barrels.filter((barrel) => {
			const code = barrel.code || '';
			const keyword = searchCode
				.trim()
				.toLowerCase();

			return code
				.toLowerCase()
				.includes(keyword);
		})
	);


	// =========================
	// Status Counts
	// =========================
	let statusCounts = $derived({
		available: filtered.filter(
			(barrel) => barrel.status === 'available'
		).length,

		rented: filtered.filter(
			(barrel) => barrel.status === 'rented'
		).length,

		returning: filtered.filter(
			(barrel) => barrel.status === 'returning'
		).length
	});


	// =========================
	// Page Load
	// =========================
	onMount(loadBarrels);
</script>


<Nav />

<main class="app-page">

	<!-- =========================
	     HEADER
	========================= -->
	<header class="page-heading">
		<div>
			<p class="eyebrow">
				RENTAL ASSETS
			</p>

			<h1>
				Barrels
			</h1>

			<p>
				See availability and current rental status at a glance.
			</p>
		</div>

		<a
			class="btn btn-primary"
			href="/barrels/create"
		>
			＋ Add barrel
		</a>
	</header>


	<!-- =========================
	     TOOLBAR
	========================= -->
	<div class="toolbar">

		<!-- Search -->
		<input
			class="control search"
			type="text"
			placeholder="Search barrel code…"
			bind:value={searchCode}
		/>


		<!-- Status -->
		<select
			class="control"
			bind:value={status}
			onchange={loadBarrels}
		>
			<option value="">
				All Status
			</option>

			<option value="available">
				Available
			</option>

			<option value="rented">
				Rented
			</option>

			<option value="returning">
				Returning
			</option>
		</select>


		<!-- View Switch -->
		<div
			class="view-switch"
			aria-label="Barrel view"
		>
			<button
				type="button"
				class:active={view === 'table'}
				onclick={() => view = 'table'}
			>
				☷ Table
			</button>

			<button
				type="button"
				class:active={view === 'visual'}
				onclick={() => view = 'visual'}
			>
				◉ Visual
			</button>
		</div>

	</div>


	<!-- =========================
	     STATUS LEGEND
	========================= -->
	{#if view === 'visual' && !loading && !errorMessage && filtered.length > 0}

		<div class="legend">
			<span>
				<i class="available"></i>
				Available
				<strong>{statusCounts.available}</strong>
			</span>

			<span>
				<i class="rented"></i>
				Rented
				<strong>{statusCounts.rented}</strong>
			</span>

			<span>
				<i class="returning"></i>
				Returning
				<strong>{statusCounts.returning}</strong>
			</span>
		</div>

	{/if}


	<!-- =========================
	     CONTENT
	========================= -->
	<section class="panel barrels-panel">

		{#if loading}

			<div class="state">
				Loading barrels…
			</div>


		{:else if errorMessage}

			<div class="state error">
				{errorMessage}
			</div>


		{:else if filtered.length === 0}

			<div class="state">
				<h3>
					No barrels found
				</h3>

				<p>
					Add a barrel or adjust your filters.
				</p>
			</div>


		{:else if view === 'visual'}

			<!-- =========================
			     VISUAL VIEW
			========================= -->
			<div class="barrel-grid">

				{#each filtered as barrel (barrel.id)}

					<article class="barrel-card {barrel.status}">

						<div
							class="barrel-picture"
							aria-hidden="true"
						>
							<div class="barrel-top"></div>

							<div class="barrel-body">
								<span class="band top"></span>

								<span class="barrel-code">
									{barrel.code}
								</span>

								<span class="band bottom"></span>
							</div>

							<div class="barrel-bottom"></div>
						</div>


						<div class="barrel-info">

							<div>
								<strong>
									Barrel {barrel.code}
								</strong>

								<span>
									{barrel.type || 'Standard barrel'}
								</span>
							</div>

							<span class="badge {barrel.status}">
								{barrel.status}
							</span>

						</div>


						<dl>
							<div>
								<dt>
									Customer
								</dt>

								<dd>
									{barrel.current_customer?.name || '—'}
								</dd>
							</div>

							<div>
								<dt>
									Invoice
								</dt>

								<dd>
									{barrel.invoice_no || '—'}
								</dd>
							</div>
						</dl>

					</article>

				{/each}

			</div>


		{:else}

			<!-- =========================
			     TABLE VIEW
			========================= -->
			<table class="data-table">

				<thead>
					<tr>
						<th>Barrel code</th>
						<th>Type</th>
						<th>Status</th>
						<th>Invoice</th>
						<th>Customer</th>
						<th class="action-column"></th>
					</tr>
				</thead>


				<tbody>

					{#each filtered as barrel (barrel.id)}

						<tr>

							<td>
								<span class="barrel-code-badge {barrel.status}">
									{barrel.code}
								</span>
							</td>


							<td>
								{barrel.type || 'Standard'}
							</td>


							<td>
								<span class="badge {barrel.status}">
									{barrel.status}
								</span>
							</td>


							<td>
								{barrel.invoice_no || '—'}
							</td>


							<td>
								{barrel.current_customer?.name || '—'}
							</td>


							<td class="action-cell">

								<div class="action-menu">

									<button
										type="button"
										class="more-button"
										onclick={() => {
											openMenuId =
												openMenuId === barrel.id
													? null
													: barrel.id;
										}}
									>
										⋯
									</button>


									{#if openMenuId === barrel.id}

										<div class="dropdown-menu">

											<button
												type="button"
												onclick={() =>
													handleStatusUpdate(
														barrel,
														'available'
													)
												}
											>
												Set Available
											</button>


											<button
												type="button"
												onclick={() =>
													handleStatusUpdate(
														barrel,
														'rented'
													)
												}
											>
												Set Rented
											</button>


											<button
												type="button"
												onclick={() =>
													handleStatusUpdate(
														barrel,
														'returning'
													)
												}
											>
												Set Returning
											</button>

										</div>

									{/if}

								</div>

							</td>

						</tr>

					{/each}

				</tbody>

			</table>

		{/if}

	</section>

</main>


<style>
	/* =========================
	   VIEW SWITCH
	========================= */

	.view-switch {
		display: flex;
		gap: 4px;
		padding: 3px;

		border: 1px solid #d9e0ea;
		border-radius: 9px;

		background: #f4f6f9;
	}

	.view-switch button {
		display: flex;
		align-items: center;
		justify-content: center;

		gap: 6px;

		min-height: 34px;
		padding: 0 11px;

		border: 0;
		border-radius: 6px;

		background: transparent;

		color: #778195;

		font-size: 12px;
		font-weight: 700;

		cursor: pointer;
	}

	.view-switch button.active {
		background: white;
		color: #315ee7;

		box-shadow:
			0 2px 7px rgb(30 52 90 / 10%);
	}


	/* =========================
	   LEGEND
	========================= */

	.legend {
		display: flex;
		justify-content: flex-end;

		gap: 18px;

		margin: -5px 2px 13px;

		color: #697386;

		font-size: 12px;
	}

	.legend span {
		display: flex;
		align-items: center;

		gap: 6px;
	}

	.legend i {
		width: 9px;
		height: 9px;

		border-radius: 50%;
	}

	.legend i.available {
		background: #26a879;
	}

	.legend i.rented {
		background: #e29a35;
	}

	.legend i.returning {
		background: #5577df;
	}

	.legend strong {
		color: #344054;
	}


	/* =========================
	   VISUAL GRID
	========================= */

	.barrel-grid {
		display: grid;

		grid-template-columns:
			repeat(auto-fill, minmax(230px, 1fr));

		gap: 18px;

		padding: 22px;

		background: #fafbfc;
	}


	/* =========================
	   BARREL CARD
	========================= */

	.barrel-card {
		overflow: hidden;

		border: 1px solid #e2e7ef;
		border-top: 4px solid var(--status-color);
		border-radius: 13px;

		background: white;

		box-shadow:
			0 4px 14px rgb(30 52 90 / 5%);
	}

	.barrel-card.available {
		--status-color: #26a879;

		--barrel-light: #d5f4e8;
		--barrel-main: #55c79d;
		--barrel-dark: #168762;
	}

	.barrel-card.rented {
		--status-color: #e29a35;

		--barrel-light: #ffebc8;
		--barrel-main: #f1b85d;
		--barrel-dark: #b87519;
	}

	.barrel-card.returning {
		--status-color: #5577df;

		--barrel-light: #dce4ff;
		--barrel-main: #7892e7;
		--barrel-dark: #3b59ba;
	}


	/* =========================
	   BARREL VISUAL
	========================= */

	.barrel-picture {
		display: flex;
		flex-direction: column;

		height: 176px;

		align-items: center;
		justify-content: center;

		background:
			linear-gradient(
				145deg,
				#f8fafc,
				#edf1f6
			);
	}

	.barrel-top,
	.barrel-bottom {
		z-index: 2;

		width: 91px;
		height: 17px;

		border: 4px solid var(--barrel-dark);
		border-radius: 50%;

		background: var(--barrel-light);
	}

	.barrel-top {
		margin-bottom: -8px;
	}

	.barrel-bottom {
		margin-top: -8px;
	}

	.barrel-body {
		position: relative;

		display: grid;

		width: 96px;
		height: 116px;

		place-items: center;

		border-right:
			5px solid var(--barrel-dark);

		border-left:
			5px solid var(--barrel-dark);

		border-radius:
			15px / 42px;

		background:
			linear-gradient(
				90deg,
				var(--barrel-dark),
				var(--barrel-main) 18%,
				var(--barrel-light) 49%,
				var(--barrel-main) 78%,
				var(--barrel-dark)
			);

		box-shadow:
			8px 10px 18px
			rgb(30 52 90 / 15%);
	}

	.band {
		position: absolute;

		right: -8px;
		left: -8px;

		height: 7px;

		border-radius: 5px;

		background: var(--barrel-dark);
	}

	.band.top {
		top: 25px;
	}

	.band.bottom {
		bottom: 25px;
	}

	.barrel-code {
		z-index: 1;

		padding: 5px 8px;

		border-radius: 5px;

		background:
			rgb(255 255 255 / 82%);

		color: #25314d;

		font-size: 13px;
		font-weight: 800;

		box-shadow:
			0 2px 6px
			rgb(30 52 90 / 12%);
	}


	/* =========================
	   TABLE BARREL CODE
	========================= */

	.barrel-code-badge {
		display: inline-flex;
		align-items: center;
		justify-content: center;

		padding: 6px 10px;

		border-radius: 8px;

		font-size: 14px;
		font-weight: 700;
	}


	/* =========================
	   STATUS COLORS
	   Barrel Code + Status
	========================= */

	.badge.available,
	.barrel-code-badge.available {
		background: #e8f8f0;
		color: #168762;
	}

	.badge.rented,
	.barrel-code-badge.rented {
		background: #fff3df;
		color: #b87519;
	}

	.badge.returning,
	.barrel-code-badge.returning {
		background: #eef2ff;
		color: #4564c8;
	}


	/* =========================
	   BARREL INFO
	========================= */

	.barrel-info {
		display: flex;

		align-items: flex-start;
		justify-content: space-between;

		gap: 10px;

		padding: 16px 16px 12px;
	}

	.barrel-info strong {
		display: block;

		color: #202a40;

		font-size: 14px;
	}

	.barrel-info div > span {
		display: block;

		margin-top: 3px;

		color: #8791a2;

		font-size: 11px;
	}

	.barrel-info > .badge {
		display: inline-flex;
	}


	/* =========================
	   DETAILS
	========================= */

	dl {
		display: grid;

		grid-template-columns:
			1fr 1fr;

		margin: 0;

		padding: 12px 16px 16px;

		border-top:
			1px solid #edf0f4;
	}

	dl div + div {
		padding-left: 14px;

		border-left:
			1px solid #edf0f4;
	}

	dt {
		margin-bottom: 4px;

		color: #8a94a6;

		font-size: 10px;
		font-weight: 700;

		text-transform: uppercase;
	}

	dd {
		overflow: hidden;

		margin: 0;

		color: #485267;

		font-size: 12px;

		text-overflow: ellipsis;
		white-space: nowrap;
	}


	/* =========================
	   TABLE ACTION
	========================= */

	.action-column {
		width: 70px;
	}

	.action-cell {
		position: relative;

		text-align: right;
	}

	.action-menu {
		position: relative;

		display: inline-block;
	}

	.more-button {
		display: inline-flex;
		align-items: center;
		justify-content: center;

		width: 38px;
		height: 36px;

		padding: 0;

		border: 1px solid #d7dce5;
		border-radius: 8px;

		background: white;
		color: #536078;

		font-size: 20px;
		font-weight: 700;

		cursor: pointer;

		transition:
			background 0.15s ease,
			border-color 0.15s ease;
	}

	.more-button:hover {
		background: #f5f7fb;
		border-color: #bfc7d4;
	}


	/* =========================
	   DROPDOWN
	========================= */

	.dropdown-menu {
		position: absolute;

		right: 0;
		bottom: 42px;

		z-index: 100;

		width: 170px;

		overflow: hidden;

		border: 1px solid #e5e7eb;
		border-radius: 8px;

		background: white;

		box-shadow:
			0 10px 30px
			rgba(15, 23, 42, 0.14);
	}

	.dropdown-menu button {
		width: 100%;

		padding: 10px 14px;

		border: none;

		background: white;

		text-align: left;

		font-size: 14px;

		cursor: pointer;
	}

	.dropdown-menu button:hover {
		background: #f3f4f6;
	}


	/* =========================
	   IMPORTANT
	   Allow dropdown outside panel
	========================= */

	.barrels-panel {
		overflow: visible;
	}


	/* =========================
	   RESPONSIVE
	========================= */

	@media (max-width: 760px) {

		.view-switch {
			width: 100%;
		}

		.view-switch button {
			flex: 1;
		}

		.legend {
			justify-content: flex-start;
			flex-wrap: wrap;
		}

		.barrel-grid {
			grid-template-columns:
				repeat(
					auto-fill,
					minmax(200px, 1fr)
				);

			padding: 15px;
		}
	}
</style>