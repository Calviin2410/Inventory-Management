<script>
	import { onMount } from "svelte";
	import { page } from "$app/state";
	import { goto } from "$app/navigation";

	import { api } from "$lib/api.js";
	import Nav from "$lib/Nav.svelte";

	let invoice = $state(null);
	let customers = $state([]);

	let loading = $state(true);
	let saving = $state(false);

	let errorMessage = $state("");
	let successMessage = $state("");

	let customerId = $state("");
	let issuedDate = $state("");
	let address = $state("");
	let notes = $state("");
	let status = $state("unpaid");

	async function loadData() {
		loading = true;
		errorMessage = "";

		try {
			const id = page.params.id;

			const [invoiceData, customerData] = await Promise.all([
				api.getInvoice(id),
				api.getCustomerOptions(),
			]);

			invoice = invoiceData;

			customers = customerData ?? [];

			customerId = String(invoice.customer_id ?? "");

			issuedDate = invoice.issued_date ?? "";

			address = invoice.address ?? "";

			notes = invoice.notes ?? "";

			status = invoice.status ?? "unpaid";
		} catch (error) {
			console.error("Unable to load invoice:", error);

			if (error?.status === 403) {
				errorMessage =
					"You do not have permission to edit this invoice.";
			} else if (error?.status === 404) {
				errorMessage = "Invoice not found.";
			} else {
				errorMessage = error?.message ?? "Unable to load invoice.";
			}
		} finally {
			loading = false;
		}
	}

	async function handleSubmit(event) {
		event.preventDefault();

		if (saving) {
			return;
		}

		errorMessage = "";
		successMessage = "";
		saving = true;

		try {
			const id = page.params.id;

			const payload = {
				customer_id: Number(customerId),

				issued_date: issuedDate,

				address: address.trim() || null,

				notes: notes.trim() || null,

				status,
			};

			const updatedInvoice = await api.updateInvoice(id, payload);

			invoice = updatedInvoice;

			successMessage = "Invoice updated successfully.";

			goto(`/invoices/${id}`);
		} catch (error) {
			console.error("Unable to update invoice:", error);

			if (error?.status === 403) {
				errorMessage = "Only administrators can edit invoices.";
			} else if (error?.errors) {
				const validationMessages = Object.values(error.errors).flat();

				errorMessage = validationMessages.join(" ");
			} else {
				errorMessage = error?.message ?? "Unable to update invoice.";
			}
		} finally {
			saving = false;
		}
	}

	onMount(loadData);
</script>

<Nav />

<main class="app-page">
	<div class="page-heading">
		<div>
			<a
				class="back-link"
				href={invoice ? `/invoices/${invoice.id}` : "/invoices"}
			>
				← Back to invoice
			</a>

			<h1>Edit Invoice</h1>

			{#if invoice}
				<p class="invoice-number">
					{invoice.invoice_no}
				</p>
			{/if}
		</div>
	</div>

	{#if loading}
		<div class="card">Loading invoice...</div>
	{:else if errorMessage && !invoice}
		<div class="error-message">
			{errorMessage}
		</div>
	{:else if invoice}
		<form class="card" onsubmit={handleSubmit}>
			{#if errorMessage}
				<div class="error-message">
					{errorMessage}
				</div>
			{/if}

			{#if successMessage}
				<div class="success-message">
					{successMessage}
				</div>
			{/if}

			<div class="form-grid">
				<!-- CUSTOMER -->

				<div class="field full-width">
					<label for="customer"> Customer </label>

					<select id="customer" bind:value={customerId} required>
						<option value="" disabled> Select customer </option>

						{#each customers as customer}
							<option value={String(customer.id)}>
								{customer.name}

								{#if customer.phone}
									- {customer.phone}
								{/if}
							</option>
						{/each}
					</select>
				</div>

				<!-- ISSUED DATE -->

				<div class="field">
					<label for="issued-date"> Issued Date </label>

					<input
						id="issued-date"
						type="date"
						bind:value={issuedDate}
						required
					/>
				</div>

				<!-- STATUS -->

				<div class="field">
					<label for="status"> Status </label>

					<select id="status" bind:value={status} required>
						<option value="unpaid"> Unpaid </option>

						<option value="paid"> Paid </option>
					</select>
				</div>

				<!-- ADDRESS -->

				<div class="field full-width">
					<label for="address"> Address </label>

					<textarea
						id="address"
						rows="3"
						bind:value={address}
						placeholder="Invoice address"
					></textarea>
				</div>

				<!-- NOTES -->

				<div class="field full-width">
					<label for="notes"> Notes </label>

					<textarea
						id="notes"
						rows="4"
						bind:value={notes}
						placeholder="Optional notes"
					></textarea>
				</div>
			</div>

			<!-- ITEMS -->

			<div class="items-section">
				<div class="section-heading">
					<div>
						<h2>Invoice Items</h2>

						<p>Barrel items are view-only for now.</p>
					</div>
				</div>

				<div class="table-wrapper">
					<table>
						<thead>
							<tr>
								<th> Barrel </th>

								<th> Description </th>

								<th> Rental Start </th>

								<th> Rental End </th>
							</tr>
						</thead>

						<tbody>
							{#each invoice.items ?? [] as item}
								<tr>
									<td>
										<strong>
											{item.barrel?.code ?? "-"}
										</strong>
									</td>

									<td>
										{item.description ?? "-"}
									</td>

									<td>
										{item.rental_start ?? "-"}
									</td>

									<td>
										{item.rental_end ?? "-"}
									</td>
								</tr>
							{:else}
								<tr>
									<td colspan="4" class="empty-row">
										No invoice items.
									</td>
								</tr>
							{/each}
						</tbody>
					</table>
				</div>
			</div>

			<div class="form-actions">
				<a class="cancel-button" href={`/invoices/${invoice.id}`}>
					Cancel
				</a>

				<button class="save-button" type="submit" disabled={saving}>
					{saving ? "Saving..." : "Save Changes"}
				</button>
			</div>
		</form>
	{/if}
</main>

<style>
	.invoice-number {
		margin: 0;

		color: #667085;
	}

	.back-link {
		color: #667085;

		text-decoration: none;

		font-size: 14px;
	}

	.back-link:hover {
		color: #101828;
	}

	.card {
		padding: 24px;

		border: 1px solid #e5e7eb;
		border-radius: 12px;

		background: white;
	}

	.form-grid {
		display: grid;

		grid-template-columns: repeat(2, minmax(0, 1fr));

		gap: 20px;
	}

	.field {
		display: flex;
		flex-direction: column;

		gap: 7px;
	}

	.full-width {
		grid-column: 1 / -1;
	}

	label {
		color: #344054;

		font-size: 14px;
		font-weight: 600;
	}

	input,
	select,
	textarea {
		width: 100%;

		box-sizing: border-box;

		padding: 10px 12px;

		border: 1px solid #d0d5dd;
		border-radius: 8px;

		background: white;

		color: #101828;

		font-family: inherit;
		font-size: 14px;

		outline: none;
	}

	input:focus,
	select:focus,
	textarea:focus {
		border-color: #315ee7;

		box-shadow: 0 0 0 2px rgba(49, 94, 231, 0.1);
	}

	textarea {
		resize: vertical;
	}

	.items-section {
		margin-top: 36px;
	}

	.section-heading {
		margin-bottom: 14px;
	}

	.section-heading h2 {
		margin: 0 0 4px;

		font-size: 18px;

		color: #101828;
	}

	.section-heading p {
		margin: 0;

		color: #667085;

		font-size: 13px;
	}

	.table-wrapper {
		overflow-x: auto;

		border: 1px solid #e5e7eb;
		border-radius: 10px;
	}

	table {
		width: 100%;

		border-collapse: collapse;
	}

	th,
	td {
		padding: 13px 14px;

		border-bottom: 1px solid #e5e7eb;

		text-align: left;

		white-space: nowrap;
	}

	th {
		background: #f9fafb;

		color: #667085;

		font-size: 12px;
		font-weight: 600;

		text-transform: uppercase;
	}

	td {
		color: #344054;

		font-size: 14px;
	}

	tbody tr:last-child td {
		border-bottom: none;
	}

	.empty-row {
		padding: 25px;

		text-align: center;

		color: #667085;
	}

	.form-actions {
		display: flex;
		align-items: center;
		justify-content: flex-end;

		gap: 12px;

		margin-top: 28px;
		padding-top: 20px;

		border-top: 1px solid #e5e7eb;
	}

	.cancel-button {
		padding: 10px 16px;

		border: 1px solid #d0d5dd;
		border-radius: 8px;

		background: white;
		color: #344054;

		text-decoration: none;

		font-size: 14px;
		font-weight: 600;
	}

	.save-button {
		padding: 11px 18px;

		border: none;
		border-radius: 8px;

		background: #111827;
		color: white;

		font-weight: 600;

		cursor: pointer;
	}

	.save-button:disabled {
		opacity: 0.6;

		cursor: not-allowed;
	}

	.error-message {
		margin-bottom: 20px;

		padding: 12px 14px;

		border-radius: 8px;

		background: #fef3f2;
		color: #b42318;

		font-size: 14px;
	}

	.success-message {
		margin-bottom: 20px;

		padding: 12px 14px;

		border-radius: 8px;

		background: #ecfdf3;
		color: #027a48;

		font-size: 14px;
	}

	@media (max-width: 700px) {
		.card {
			padding: 18px;
		}

		.form-grid {
			grid-template-columns: 1fr;
		}

		.full-width {
			grid-column: auto;
		}

		.form-actions {
			flex-direction: column-reverse;

			align-items: stretch;
		}

		.cancel-button,
		.save-button {
			text-align: center;
		}
	}
</style>
