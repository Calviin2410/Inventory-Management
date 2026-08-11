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
			errorMessage = error?.message || 'Unable to load barrels';
		} finally {
			loading = false;
		}
	}

	// =========================
	// Search Filter
	// =========================
	let filtered = $derived(
		barrels.filter((barrel) => {
			const code = barrel.code || '';
			const keyword = searchCode.toLowerCase();

			return code
				.toLowerCase()
				.includes(keyword);
		})
	);

	// =========================
	// Status Totals
	// =========================
	let statusCounts = $derived({
		available: filtered.filter((barrel) => barrel.status === 'available').length,
		rented: filtered.filter((barrel) => barrel.status === 'rented').length,
		returning: filtered.filter((barrel) => barrel.status === 'returning').length
	});

	// =========================
	// Page Load
	// =========================
	onMount(loadBarrels);
</script>

<Nav />

<main class="app-page">
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

	<div class="toolbar">
		<input
			class="control search"
			placeholder="Search barrel code…"
			bind:value={searchCode}
		/>

		<select
			class="control"
			bind:value={status}
			onchange={loadBarrels}
		>
			<option value="">
				All statuses
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

		<div class="view-switch" aria-label="Barrel view">
			<button
				class:active={view === 'table'}
				type="button"
				onclick={() => (view = 'table')}
			>
				<span aria-hidden="true">☷</span> Table
			</button>

			<button
				class:active={view === 'visual'}
				type="button"
				onclick={() => (view = 'visual')}
			>
				<span aria-hidden="true">◉</span> Visual
			</button>
		</div>
	</div>

	{#if view === 'visual' && !loading && !errorMessage && filtered.length > 0}
		<div class="legend" aria-label="Barrel status legend">
			<span><i class="available"></i>Available <strong>{statusCounts.available}</strong></span>
			<span><i class="rented"></i>Rented <strong>{statusCounts.rented}</strong></span>
			<span><i class="returning"></i>Returning <strong>{statusCounts.returning}</strong></span>
		</div>
	{/if}

	<section class="panel">
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
			<div class="barrel-grid">
				{#each filtered as barrel (barrel.id)}
					<article class="barrel-card {barrel.status}">
						<div class="barrel-picture" aria-hidden="true">
							<div class="barrel-top"></div>
							<div class="barrel-body">
								<span class="band top"></span>
								<span class="barrel-code">{barrel.code}</span>
								<span class="band bottom"></span>
							</div>
							<div class="barrel-bottom"></div>
						</div>
						<div class="barrel-info">
							<div><strong>Barrel {barrel.code}</strong><span>{barrel.type || 'Standard barrel'}</span></div>
							<span class="badge {barrel.status}">{barrel.status}</span>
						</div>
						<dl>
							<div><dt>Customer</dt><dd>{barrel.current_customer?.name || '—'}</dd></div>
							<div><dt>Invoice</dt><dd>{barrel.invoice_no || '—'}</dd></div>
						</dl>
					</article>
				{/each}
			</div>
		{:else}
			<table class="data-table">
				<thead>
					<tr>
						<th>Barrel code</th>
						<th>Type</th>
						<th>Status</th>
						<th>Invoice</th>
						<th>Customer</th>
					</tr>
				</thead>

				<tbody>
					{#each filtered as barrel (barrel.id)}
						<tr>
							<td class="strong">
								{barrel.code}
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
						</tr>
					{/each}
				</tbody>
			</table>
		{/if}
	</section>
</main>

<style>
	.view-switch {
		display: flex;
		padding: 3px;
		border: 1px solid #d9e0ea;
		border-radius: 9px;
		background: #f4f6f9;
	}
	.view-switch button {
		display: flex;
		min-height: 34px;
		align-items: center;
		gap: 6px;
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
		background: #fff;
		color: #315ee7;
		box-shadow: 0 2px 7px rgb(30 52 90 / 10%);
	}
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
	.barrel-grid {
		display: grid;
		grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
		gap: 18px;
		padding: 22px;
		background: #fafbfc;
	}
	.barrel-card {
		overflow: hidden;
		border: 1px solid #e2e7ef;
		border-top: 4px solid var(--status-color);
		border-radius: 13px;
		background: #fff;
		box-shadow: 0 4px 14px rgb(30 52 90 / 5%);
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
	.barrel-picture {
		display: flex;
		height: 176px;
		align-items: center;
		flex-direction: column;
		justify-content: center;
		background: linear-gradient(145deg, #f8fafc, #edf1f6);
	}
	.barrel-top, .barrel-bottom {
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
		border-right: 5px solid var(--barrel-dark);
		border-left: 5px solid var(--barrel-dark);
		border-radius: 15px / 42px;
		background: linear-gradient(90deg, var(--barrel-dark), var(--barrel-main) 18%, var(--barrel-light) 49%, var(--barrel-main) 78%, var(--barrel-dark));
		box-shadow: 8px 10px 18px rgb(30 52 90 / 15%);
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
		background: rgb(255 255 255 / 82%);
		color: #25314d;
		font-size: 13px;
		font-weight: 850;
		box-shadow: 0 2px 6px rgb(30 52 90 / 12%);
	}
	.barrel-info {
		display: flex;
		align-items: flex-start;
		justify-content: space-between;
		gap: 10px;
		padding: 16px 16px 12px;
	}
	.barrel-info strong, .barrel-info span {
		display: block;
	}
	.barrel-info strong {
		color: #202a40;
		font-size: 14px;
	}
	.barrel-info div > span {
		margin-top: 3px;
		color: #8791a2;
		font-size: 11px;
	}
	.barrel-info > .badge {
		display: inline-flex;
	}
	dl {
		display: grid;
		grid-template-columns: 1fr 1fr;
		margin: 0;
		padding: 12px 16px 16px;
		border-top: 1px solid #edf0f4;
	}
	dl div + div {
		padding-left: 14px;
		border-left: 1px solid #edf0f4;
	}
	dt {
		margin-bottom: 4px;
		color: #8a94a6;
		font-size: 10px;
		font-weight: 750;
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
	@media (max-width: 760px) {
		.view-switch {
			width: 100%;
		}
		.view-switch button {
			flex: 1;
			justify-content: center;
		}
		.legend {
			justify-content: flex-start;
			flex-wrap: wrap;
		}
		.barrel-grid {
			grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
			padding: 15px;
		}
	}
</style>
