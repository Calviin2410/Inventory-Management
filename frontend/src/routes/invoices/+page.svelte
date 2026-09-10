<script>
	import { onMount } from 'svelte';
	import { api } from '$lib/api.js';
	import { openInvoicePrintWindow } from '$lib/invoicePrint.js';
	import Nav from '$lib/Nav.svelte';

	// =========================
	// State
	// =========================
	let invoices = $state([]);
	let loading = $state(true);
	let errorMessage = $state('');
	let search = $state('');

	let openMenuId = $state(null);
	let exportingId = $state(null);

	// =========================
	// Load Invoices
	// =========================
	async function loadInvoices() {
		loading = true;
		errorMessage = '';

		try {
			const result =
				await api.getInvoices();

			invoices = Array.isArray(result)
				? result
				: result?.data ?? [];

		} catch (error) {
			errorMessage =
				error?.message ||
				'Unable to load invoices';
		} finally {
			loading = false;
		}
	}


	// =========================
	// Search Filter
	// =========================
	let filtered = $derived(
		invoices.filter((invoice) => {
			const invoiceNo =
				invoice.invoice_no || '';

			const customerName =
				invoice.customer?.name || '';

			const keyword =
				search.trim().toLowerCase();

			return `${invoiceNo} ${customerName}`
				.toLowerCase()
				.includes(keyword);
		})
	);

	async function updateInvoiceStatus(
		invoice,
		newStatus
	) {
		try {

			const updated = await api.updateInvoice(invoice.id, { status: newStatus });
			invoices = invoices.map((item) => item.id === invoice.id ? updated : item);

			openMenuId = null;
		} catch (error) {
			errorMessage =
				error?.message ||
				'Unable to update invoice status';
		}
	}


	// =========================
	// Export PDF
	// =========================
	async function exportPdf(invoice) {
		const printWindow = window.open('', '_blank');

		if (!printWindow) {
			errorMessage = 'Please allow pop-ups to export this invoice as PDF.';
			return;
		}

		printWindow.document.write('<p style="font:14px sans-serif;padding:24px">Preparing invoice…</p>');
		exportingId = invoice.id;
		openMenuId = null;

		errorMessage = '';

		try {
			const fullInvoice = await api.getInvoice(invoice.id);
			openInvoicePrintWindow(printWindow, fullInvoice);
		} catch (error) {
			printWindow.close();
			errorMessage = error?.message || 'Unable to export invoice PDF';
		} finally {
			exportingId = null;
		}
	}


	// =========================
	// Page Load
	// =========================
	onMount(loadInvoices);
</script>


<Nav />


<main class="app-page">

	<!-- =========================
	     HEADER
	========================= -->
	<header class="page-heading">

		<div>
			<p class="eyebrow">
				SALES
			</p>

			<h1>
				Invoices
			</h1>

			<p>
				Create, review and track rental invoices.
			</p>
		</div>

		<a
			class="btn btn-primary"
			href="/invoices/create"
		>
			＋ Create invoice
		</a>

	</header>


	<!-- =========================
	     TOOLBAR
	========================= -->
	<div class="toolbar">

		<input
			class="control search"
			placeholder="Search invoice or customer…"
			bind:value={search}
		/>

		<button
			type="button"
			class="btn"
			onclick={loadInvoices}
		>
			Refresh
		</button>

	</div>


	<!-- =========================
	     INVOICE TABLE
	========================= -->
	<section class="panel invoice-panel">

		{#if loading}

			<div class="state">
				Loading invoices…
			</div>


		{:else if errorMessage}

			<div class="state error">
				{errorMessage}
			</div>


		{:else if filtered.length === 0}

			<div class="state">

				<h3>
					No invoices found
				</h3>

				<p>
					Try another search or create a new invoice.
				</p>

			</div>


		{:else}

			<table class="data-table">

				<thead>
					<tr>
						<th>
							Invoice
						</th>

						<th>
							Customer
						</th>

						<th>
							Issued date
						</th>

						<th class="status-column">
							Status
						</th>

						<th class="action-column"></th>
					</tr>
				</thead>


				<tbody>

					{#each filtered as invoice (invoice.id)}

						<tr>

							<!-- Invoice Number -->
							<td class="strong">

								<a href={`/invoices/${invoice.id}`}>
									{invoice.invoice_no}
								</a>

							</td>


							<!-- Customer -->
							<td>
								{invoice.customer?.name || 'Walk-in customer'}
							</td>


							<!-- Issued Date -->
							<td>
								{invoice.issued_date}
							</td>


							<!-- Status -->
							<td class="status-cell">

								<span class="badge {invoice.status}">
									{invoice.status}
								</span>

							</td>


							<!-- Action -->
							<td class="action-cell">

									<div class="action-menu">

										<button
											type="button"
											class="more-button"
											onclick={() => {
												openMenuId =
													openMenuId === invoice.id
														? null
														: invoice.id;
											}}
										>
											⋯
										</button>

										{#if openMenuId === invoice.id}

											<div class="dropdown-menu">

												<a
													class="menu-link"
													href={`/invoices/${invoice.id}`}
												>
													View invoice
												</a>

												<a
													class="menu-link"
													href={`/invoices/${invoice.id}/edit`}
												>
													Edit invoice
												</a>

												<div class="menu-divider"></div>

												{#if invoice.status !== 'paid'}

													<button
														type="button"
														onclick={() =>
															updateInvoiceStatus(
																invoice,
																'paid'
															)
														}
													>
														Set Paid
													</button>

												{/if}

												{#if invoice.status !== 'unpaid'}

													<button
														type="button"
														onclick={() =>
															updateInvoiceStatus(
																invoice,
																'unpaid'
															)
														}
													>
														Set Unpaid
													</button>

												{/if}

												<div class="menu-divider"></div>

												<button
													type="button"
													disabled={
														exportingId === invoice.id
													}
													onclick={() =>
														exportPdf(invoice)
													}
												>
													{exportingId === invoice.id
														? 'Preparing…'
														: 'Export PDF'}
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
	   STATUS COLUMN
	========================= */

	.status-column,
	.status-cell {
		width: 160px;
	}


	/* =========================
	   STATUS BADGE
	========================= */

	.badge {
		display: inline-flex;
		align-items: center;
		justify-content: center;

		min-width: 64px;

		text-align: center;
	}


	/* Unpaid = Red */
	.badge.unpaid {
		background: #fee2e2;
		color: #dc2626;
	}


	/* Paid = Teal */
	.badge.paid {
		background: #ccfbf1;
		color: #0f766e;
	}


	/* =========================
	   ACTION COLUMN
	========================= */

	.action-column,
	.action-cell {
		width: 80px;
	}


	.action-cell {
		position: relative;

		text-align: right;
	}


	.action-menu {
		position: relative;

		display: inline-block;
	}


	/* =========================
	   THREE DOT BUTTON
	========================= */

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

	.menu-link {
		display: block;

		width: 100%;

		padding: 11px 14px;

		color: #202939;
		background: white;

		text-align: left;
		text-decoration: none;

		font-size: 14px;
		font-weight: 500;
	}

	.menu-link:hover {
		background: #f3f4f6;
	}

	.more-button:hover {
		background: #f5f7fb;
		border-color: #bfc7d4;
	}


	/* =========================
	   ACTION DROPDOWN
	========================= */

	.dropdown-menu {
		position: absolute;

		top: 42px;
		right: 0;

		z-index: 1000;

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
		display: block;

		width: 100%;

		padding: 11px 14px;

		border: none;

		background: white;

		color: #202939;

		text-align: left;

		font-size: 14px;
		font-weight: 500;

		cursor: pointer;
	}


	.dropdown-menu button:hover {
		background: #f3f4f6;
	}


	.menu-divider {
		height: 1px;

		background: #e5e7eb;
	}


	/* =========================
	   IMPORTANT
	   Allow dropdown outside panel
	========================= */

	.invoice-panel {
		overflow: visible;
	}


	/* =========================
	   RESPONSIVE
	========================= */

	@media (max-width: 760px) {

		.status-column,
		.status-cell {
			width: auto;
		}


		.action-column,
		.action-cell {
			width: 60px;
		}


		.dropdown-menu {
			right: 0;

			width: 150px;
		}
	}
</style>