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

	// =========================
	// Load barrels from API
	// =========================
	async function loadBarrels() {
		loading = true;
		errorMessage = '';

		try {
			const params = status ? { status } : {};

			barrels = await api.getBarrels(params);
		} catch (error) {
			errorMessage =
				error?.message || 'Unable to load barrels';
		} finally {
			loading = false;
		}
	}

	// =========================
	// Search filter
	// =========================
	let filtered = $derived(
		barrels.filter((barrel) => {
			const code = barrel.code || '';

			return code
				.toLowerCase()
				.includes(searchCode.toLowerCase());
		})
	);

	// =========================
	// Page loaded
	// =========================
	onMount(loadBarrels);
</script>

<Nav />

<main class="app-page">
	<header class="page-heading">
		<div>
			<p class="eyebrow">RENTAL ASSETS</p>

			<h1>Barrels</h1>

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
				All Status
			</option>

			<option value="available">
				Available
			</option>

			<option value="issued">
				Issued
			</option>

			<option value="returned">
				Returned
			</option>
		</select>
	</div>

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
				<h3>No barrels found</h3>

				<p>
					Add a barrel or adjust your filters.
				</p>
			</div>

		{:else}

			<table class="data-table">
				<thead>
					<tr>
						<th>Barrel code</th>
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