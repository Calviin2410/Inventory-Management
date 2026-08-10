<script>
	import { onMount } from 'svelte';
	import { api } from '$lib/api.js';
	import Nav from '$lib/Nav.svelte';

	let customers = $state([]);
	let loading = $state(true);
	let errorMessage = $state('');

	async function loadCustomers() {
		loading = true;
		errorMessage = '';

		try {
			customers = await api.getCustomers();
		} catch (error) {
			errorMessage =
				error instanceof Error
					? error.message
					: 'Failed to load customers';
		} finally {
			loading = false;
		}
	}

	onMount(loadCustomers);
</script>

<Nav />

<div class="page">

	<div class="page-header">
		<h1>Customers</h1>
	</div>

	{#if loading}
		<p>Loading...</p>

	{:else if errorMessage}
		<p class="error">
			{errorMessage}
		</p>

	{:else if customers.length === 0}
		<p class="empty">
			No customers yet.
		</p>

	{:else}

		<table>
			<thead>
				<tr>
					<th>Customer ID</th>
					<th>Name</th>
					<th>Phone</th>
					<th>Address</th>
				</tr>
			</thead>

			<tbody>
				{#each customers as customer}
					<tr>
						<td>
							{customer.id}
						</td>

						<td>
							{customer.name}
						</td>

						<td>
							{customer.phone ?? '-'}
						</td>

						<td>
							{customer.address ?? '-'}
						</td>
					</tr>
				{/each}
			</tbody>
		</table>

	{/if}

</div>

<style>
	.page {
		max-width: 1100px;
		margin: 32px 0 60px 50px;
		padding-right: 40px;
		font-family: Arial, Helvetica, sans-serif;
	}

	.page-header {
		margin-bottom: 24px;
	}

	h1 {
		margin: 0;
		font-size: 32px;
	}

	table {
		width: 100%;
		border-collapse: collapse;
		border: 1px solid #e5e7eb;
	}

	th,
	td {
		padding: 12px 14px;
		text-align: left;
		border-bottom: 1px solid #e5e7eb;
	}

	th {
		background: #f8fafc;
		font-size: 14px;
	}

	td {
		font-size: 14px;
	}

	.error {
		color: #dc2626;
	}

	.empty {
		color: #64748b;
	}
</style>