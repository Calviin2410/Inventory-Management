<script>
	import { onMount } from 'svelte';
	import { goto } from '$app/navigation';
	import { api } from '$lib/api.js';
	import Nav from '$lib/Nav.svelte';

	let invoices = $state([]);
	let loading = $state(true);
	let errorMessage = $state('');

	async function loadInvoices() {
		loading = true;
		errorMessage = '';

		try {
			const result = await api.getInvoices();

			console.log('Invoices API result:', result);

			invoices = result.data ?? result ?? [];
		} catch (error) {
			console.error('Load invoices failed:', error);

			errorMessage =
				error instanceof Error
					? error.message
					: '加载发票失败';
		} finally {
			loading = false;
		}
	}

	onMount(loadInvoices);
</script>

<Nav />

<div class="page">

	<div class="page-header">
		<h1>发票</h1>

		<button
			type="button"
			on:click={() => goto('/invoices/create')}
		>
			+ Create Invoice
		</button>
	</div>


	{#if loading}

		<p class="loading">
			加载中...
		</p>

	{:else if errorMessage}

		<p class="error">
			{errorMessage}
		</p>

	{:else if invoices.length === 0}

		<div class="empty-state">
			<p>还没有发票记录。</p>

			<a href="/invoices/create">
				创建第一张发票
			</a>
		</div>

	{:else}

		<div class="table-card">

			<table>

				<thead>
					<tr>
						<th>
							发票号
						</th>

						<th>
							客户
						</th>

						<th>
							日期
						</th>

						<th>
							状态
						</th>
					</tr>
				</thead>

				<tbody>

					{#each invoices as invoice (invoice.id)}

						<tr>

							<td class="invoice-number">
								{invoice.invoice_no}
							</td>

							<td>
								{invoice.customer?.name ?? '-'}
							</td>

							<td>
								{invoice.issued_date}
							</td>

							<td>

								<span
									class="status-badge"
									class:paid={invoice.status === 'paid'}
									class:unpaid={invoice.status === 'unpaid'}
								>
									{invoice.status === 'paid'
										? '已付款'
										: '未付款'}
								</span>

							</td>

						</tr>

					{/each}

				</tbody>

			</table>

		</div>

	{/if}

</div>

<style>
	.page {
		max-width: 960px;
		margin: 2rem auto;
		padding: 0 1rem;
		font-family: sans-serif;
	}
	.header {
		display: flex;
		justify-content: space-between;
		align-items: center;
		margin-bottom: 1rem;
	}
	.create-btn {
		background: #2563eb;
		color: white;
		padding: 0.5rem 1rem;
		border-radius: 4px;
		text-decoration: none;
		font-size: 0.9rem;
	}
	table {
		width: 100%;
		border-collapse: collapse;
	}
	th,
	td {
		text-align: left;
		padding: 0.5rem;
		border-bottom: 1px solid #eee;
		font-size: 0.9rem;
	}
	.status {
		padding: 0.1rem 0.5rem;
		border-radius: 4px;
		font-size: 0.75rem;
	}
	.status.unpaid {
		background: #fef2f2;
		color: #dc2626;
	}
	.status.paid {
		background: #f0fdf4;
		color: #16a34a;
	}
	.empty {
		color: #64748b;
	}
	.error {
		color: #dc2626;
	}

	.loading {
		color: #64748b;
		font-size: 15px;
	}

	.error {
		color: #dc2626;
		font-size: 14px;
	}

	.empty-state {
		padding: 30px;

		border: 1px solid #e5e7eb;
		border-radius: 8px;

		background: white;

		color: #64748b;
	}

	.empty-state p {
		margin: 0 0 10px 0;
	}

	.empty-state a {
		color: #2563eb;
		text-decoration: none;
		font-weight: 500;
	}

	.empty-state a:hover {
		text-decoration: underline;
	}

	.table-card {
		border: 1px solid #e5e7eb;
		border-radius: 8px;

		overflow: hidden;

		background: white;
	}

	table {
		width: 100%;
		border-collapse: collapse;
	}

	th {
		padding: 15px 18px;

		background: #f8fafc;

		border-bottom: 1px solid #e5e7eb;

		text-align: left;

		font-size: 14px;
		font-weight: 600;

		color: #374151;
	}

	td {
		padding: 16px 18px;

		border-bottom: 1px solid #e5e7eb;

		font-size: 14px;

		color: #111827;
	}

	tbody tr:last-child td {
		border-bottom: none;
	}

	tbody tr:hover {
		background: #f9fafb;
	}

	.invoice-number {
		font-weight: 600;
	}

	.status-badge {
		display: inline-block;

		padding: 5px 10px;

		border-radius: 5px;

		font-size: 13px;
		font-weight: 600;
	}

	.status-badge.paid {
		background: #dcfce7;
		color: #15803d;
	}

	.status-badge.unpaid {
		background: #fee2e2;
		color: #b91c1c;
	}
</style>
