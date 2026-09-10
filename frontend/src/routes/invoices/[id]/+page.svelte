<script>
	import { onMount } from 'svelte';
	import { page } from '$app/state';
	import { goto } from '$app/navigation';

	import { api } from '$lib/api.js';
	import Nav from '$lib/Nav.svelte';

	let invoice = null;
	let loading = true;
	let errorMessage = '';

	onMount(async () => {
		// 前端再挡一次 normal staff
		if (!isAdmin) {
			goto('/invoices');
			return;
		}

		await loadInvoice();
	});

	async function loadInvoice() {
		loading = true;
		errorMessage = '';

		try {
			const id = page.params.id;

			invoice = await api.getInvoice(id);
		} catch (error) {
			console.error(error);

			if (error?.status === 403) {
				errorMessage =
					'You do not have permission to view this invoice.';
			} else {
				errorMessage =
					error?.message ??
					'Unable to load invoice.';
			}
		} finally {
			loading = false;
		}
	}

	function formatMoney(value) {
		return Number(value ?? 0).toFixed(2);
	}
</script>

<Nav />

<div class="page">

	<div class="page-header">
		<div>
			<a
				class="back-link"
				href="/invoices"
			>
				← Back to invoices
			</a>

			<h1>
				{invoice?.invoice_no ?? 'Invoice'}
			</h1>
		</div>

		{#if invoice}
			<a
				class="edit-button"
				href={`/invoices/${invoice.id}/edit`}
			>
				Edit Invoice
			</a>
		{/if}
	</div>

	{#if loading}

		<p>Loading invoice...</p>

	{:else if errorMessage}

		<p class="error">
			{errorMessage}
		</p>

	{:else if invoice}

		<div class="invoice-card">

			<div class="detail-grid">

				<div>
					<span class="label">
						Invoice Number
					</span>

					<p>
						{invoice.invoice_no}
					</p>
				</div>

				<div>
					<span class="label">
						Status
					</span>

					<p>
						<span
							class="status {invoice.status}"
						>
							{invoice.status}
						</span>
					</p>
				</div>

				<div>
					<span class="label">
						Customer
					</span>

					<p>
						{invoice.customer?.name ?? '-'}
					</p>
				</div>

				<div>
					<span class="label">
						Phone
					</span>

					<p>
						{invoice.customer?.phone ?? '-'}
					</p>
				</div>

				<div>
					<span class="label">
						Issued Date
					</span>

					<p>
						{invoice.issued_date ?? '-'}
					</p>
				</div>

				<div>
					<span class="label">
						Total Amount
					</span>

					<p>
						RM {formatMoney(
							invoice.total_amount
						)}
					</p>
				</div>

			</div>

			{#if invoice.address}

				<div class="section">
					<span class="label">
						Address
					</span>

					<p>
						{invoice.address}
					</p>
				</div>

			{/if}

			{#if invoice.notes}

				<div class="section">
					<span class="label">
						Notes
					</span>

					<p>
						{invoice.notes}
					</p>
				</div>

			{/if}

			<div class="section">

				<h2>
					Invoice Items
				</h2>

				<div class="table-wrapper">

					<table>

						<thead>
							<tr>
								<th>Barrel</th>
								<th>Description</th>
								<th>Rental Start</th>
								<th>Rental End</th>
								<th>Unit Price</th>
							</tr>
						</thead>

						<tbody>

							{#each invoice.items ?? [] as item}

								<tr>
									<td>
										{item.barrel?.code ?? '-'}
									</td>

									<td>
										{item.description ?? '-'}
									</td>

									<td>
										{item.rental_start ?? '-'}
									</td>

									<td>
										{item.rental_end ?? '-'}
									</td>

									<td>
										RM {formatMoney(
											item.unit_price
										)}
									</td>
								</tr>

							{/each}

						</tbody>

					</table>

				</div>

			</div>

		</div>

	{/if}

</div>

<style>
	.page {
		max-width: 1180px;
		margin: 0 auto;
		padding: 32px;
	}

	.page-header {
		display: flex;
		justify-content: space-between;
		align-items: center;
		gap: 20px;
		margin-bottom: 24px;
	}

	.page-header h1 {
		margin: 8px 0 0;
	}

	.back-link {
		color: #667085;
		text-decoration: none;
		font-size: 14px;
	}

	.edit-button {
		padding: 10px 16px;
		border-radius: 8px;
		background: #111827;
		color: white;
		text-decoration: none;
		font-weight: 600;
	}

	.invoice-card {
		background: white;
		border: 1px solid #e5e7eb;
		border-radius: 12px;
		padding: 24px;
	}

	.detail-grid {
		display: grid;
		grid-template-columns:
			repeat(2, minmax(0, 1fr));
		gap: 20px;
	}

	.label {
		display: block;
		color: #667085;
		font-size: 13px;
		margin-bottom: 6px;
	}

	.detail-grid p,
	.section p {
		margin: 0;
		color: #101828;
	}

	.section {
		margin-top: 30px;
	}

	.section h2 {
		margin-bottom: 16px;
		font-size: 18px;
	}

	.table-wrapper {
		overflow-x: auto;
	}

	table {
		width: 100%;
		border-collapse: collapse;
	}

	th,
	td {
		padding: 12px;
		text-align: left;
		border-bottom: 1px solid #e5e7eb;
	}

	th {
		color: #667085;
		font-size: 13px;
	}

	.status {
		display: inline-block;
		padding: 4px 9px;
		border-radius: 999px;
		font-size: 12px;
		font-weight: 600;
		text-transform: capitalize;
	}

	.status.paid {
		background: #ecfdf3;
		color: #027a48;
	}

	.status.unpaid {
		background: #fef3f2;
		color: #b42318;
	}

	.status.cancelled {
		background: #f2f4f7;
		color: #475467;
	}

	.error {
		color: #b42318;
	}

	@media (max-width: 700px) {
		.page {
			padding: 20px;
		}

		.detail-grid {
			grid-template-columns: 1fr;
		}

		.page-header {
			align-items: flex-start;
		}
	}
</style>