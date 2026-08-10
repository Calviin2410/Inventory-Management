<script>
    import { onMount } from 'svelte';
    import { api } from '$lib/api.js';
    import Nav from '$lib/Nav.svelte';
	import { goto } from '$app/navigation';

    let invoiceId = $state('');

	let selectedCustomerId = $state(null);

    let customerName = $state('');

	let phone = $state('');

    let rentalStart = $state(
        new Date().toISOString().slice(0, 10)
    );

    let rentalEnd = $state('');

    let address = $state('');

    let barrel = $state('');
    let description = $state('');

    async function loadInvoiceNumber() {
        try {
            const result = await api.getNextInvoiceNo();

            invoiceId = result.invoice_no;
        } catch (error) {
            console.error(
                'Failed to load invoice number:',
                error
            );
        }
    }

	async function handleSubmit() {
		try {
			/*
			* 1. Basic validation
			*/
			if (!customerName.trim()) {
				alert('Please enter customer name');
				return;
			}

			if (!barrel.trim()) {
				alert('Please enter barrel code');
				return;
			}

			if (!rentalStart) {
				alert('Please select rental start date');
				return;
			}


			/*
			* 2. Find customer
			*/
			const customers = await api.getCustomers({
				search: customerName.trim()
			});

			let customer = customers.find(
				(item) =>
					item.name.toLowerCase() ===
					customerName.trim().toLowerCase()
			);


			/*
			* No customer found → create new customer
			*/
			if (!customer) {
				customer = await api.createCustomer({
					name: customerName.trim(),
					phone: phone.trim() || null
				});
			}


			/*
			* 3. Find barrel by code
			*/
			const barrels = await api.getBarrels();

			const selectedBarrel = barrels.find(
				(item) =>
					item.code.toLowerCase() ===
					barrel.trim().toLowerCase()
			);


			if (!selectedBarrel) {
				alert('Barrel not found');
				return;
			}


			if (selectedBarrel.status !== 'available') {
				alert('This barrel is not available');
				return;
			}


			/*
			* 4. Create invoice
			*/
			const invoice = await api.createInvoice({
				customer_id: customer.id,

				issued_date: rentalStart,

				address: address.trim() || null,

				notes: null,

				items: [
					{
						barrel_id: selectedBarrel.id,

						description:
							description.trim() || null,

						rental_start: rentalStart,

						rental_end:
							rentalEnd || null
					}
				]
			});


			console.log(
				'Invoice created:',
				invoice
			);


			/*
			* 5. Redirect
			*/
			goto('/invoices');

		} catch (error) {

			console.error(
				'Create invoice failed:',
				error
			);

			alert(
				error instanceof Error
					? error.message
					: 'Failed to create invoice'
			);
		}
	}

    onMount(() => {
        loadInvoiceNumber();
    });
</script>

<Nav />

<div class="page">

	<!-- Page Title -->
	<div class="page-header">
		<h1>Create Invoice</h1>
	</div>

	<!-- ========================= -->
	<!-- PART 1 : Invoice Details -->
	<!-- ========================= -->

	<section class="invoice-section">

		<div class="section-title">
			Invoice Details
		</div>

		<div class="form-grid">

			<!-- Invoice ID -->
			<div class="form-group">
				<label for="invoiceId">
					Invoice ID
				</label>

				<input
					id="invoiceId"
					type="text"
					bind:value={invoiceId}
					readonly
				/>
			</div>


			<!-- Customer Name -->
			<div class="form-group">
				<label for="customerName">
					Customer Name
				</label>

				<input
					id="customerName"
					type="text"
					placeholder="Enter customer name"
					bind:value={customerName}
				/>
			</div>


			<div class="form-group">
				<label for="phone">
					Phone
					<span class="optional">
						(Optional)
					</span>
				</label>

				<input
					id="phone"
					type="tel"
					placeholder="Enter phone number"
					bind:value={phone}
				/>
			</div>

			<!-- Rental Start -->
			<div class="form-group">
				<label for="rentalStart">
					Rental Start
				</label>

				<input
					id="rentalStart"
					type="date"
					bind:value={rentalStart}
				/>
			</div>


			<!-- Rental End -->
			<div class="form-group">
				<label for="rentalEnd">
					Rental End
				</label>

				<input
					id="rentalEnd"
					type="date"
					bind:value={rentalEnd}
				/>
			</div>


			<!-- Address -->
			<div class="form-group full-width">
				<label for="address">
					Address
				</label>

				<textarea
					id="address"
					rows="3"
					placeholder="Enter customer address"
					bind:value={address}
				></textarea>
			</div>

		</div>

	</section>


	<!-- ========================= -->
	<!-- PART 2 : Invoice Items   -->
	<!-- ========================= -->

	<section class="invoice-section">

		<div class="section-title">
			Invoice Items
		</div>

		<div class="table-wrapper">

			<table>

				<thead>
					<tr>
						<th class="barrel-column">
							Barrel
						</th>

						<th>
							Description
						</th>
					</tr>
				</thead>


				<tbody>
					<tr>

						<td>
							<input
								type="text"
								placeholder="Barrel"
								bind:value={barrel}
							/>
						</td>

						<td>
							<input
								type="text"
								placeholder="Description"
								bind:value={description}
							/>
						</td>

					</tr>
				</tbody>

			</table>

		</div>

	</section>


	<!-- Button -->
	<button
		type="button"
		class="create-button"
		on:click={handleSubmit}
	>
		Create Invoice
	</button>

</div>


<style>

	/* ========================================
	   MAIN PAGE
	======================================== */

	.page {
		max-width: 1100px;

		/*
		 * 不再使用 auto 把整个页面放正中央
		 * 左边留一点空间
		 */
		margin: 32px 0 60px 50px;

		padding-right: 40px;

		font-family:
			Arial,
			Helvetica,
			sans-serif;
	}


	/* ========================================
	   TITLE
	======================================== */

	.page-header {
		margin-bottom: 28px;
	}

	.page-header h1 {
		margin: 0;

		font-size: 32px;
		font-weight: 700;

		color: #111827;
	}


	/* ========================================
	   SECTION
	======================================== */

	.invoice-section {
		margin-bottom: 32px;

		border: 1px solid #e5e7eb;
		border-radius: 8px;

		background: white;
	}


	.section-title {
		padding: 15px 20px;

		border-bottom: 1px solid #e5e7eb;

		background: #f8fafc;

		font-size: 16px;
		font-weight: 600;

		color: #1f2937;
	}


	/* ========================================
	   TOP FORM
	======================================== */

	.form-grid {
		display: grid;

		grid-template-columns:
			repeat(2, minmax(0, 1fr));

		gap: 20px 28px;

		padding: 24px;
	}


	.form-group {
		display: flex;
		flex-direction: column;

		gap: 7px;
	}


	.form-group label {
		font-size: 14px;
		font-weight: 600;

		color: #374151;
	}


	.form-group input,
	.form-group textarea {
		width: 100%;

		box-sizing: border-box;

		padding: 11px 12px;

		border: 1px solid #d1d5db;
		border-radius: 5px;

		font-size: 14px;

		outline: none;

		background: white;
	}


	.form-group input:focus,
	.form-group textarea:focus {
		border-color: #2563eb;

		box-shadow:
			0 0 0 1px #2563eb;
	}


	.form-group input[readonly] {
		background: #f3f4f6;

		color: #4b5563;
	}


	.form-group textarea {
		resize: vertical;
	}


	.full-width {
		grid-column: 1 / -1;
	}


	/* ========================================
	   TABLE
	======================================== */

	.table-wrapper {
		padding: 0;
		overflow-x: auto;
	}


	table {
		width: 100%;

		border-collapse: collapse;
	}


	th {
		padding: 13px 15px;

		background: #f8fafc;

		border-bottom: 1px solid #e5e7eb;

		text-align: left;

		font-size: 14px;
		font-weight: 600;

		color: #374151;
	}


	td {
		padding: 12px 15px;

		border-bottom: 1px solid #e5e7eb;
	}


	td input {
		width: 100%;

		box-sizing: border-box;

		padding: 10px 11px;

		border: 1px solid #d1d5db;
		border-radius: 5px;

		font-size: 14px;

		outline: none;
	}


	td input:focus {
		border-color: #2563eb;
	}


	.barrel-column {
		width: 30%;
	}


	/* ========================================
	   BUTTON
	======================================== */

	.actions {
		display: flex;

		justify-content: flex-end;

		margin-top: 20px;
	}


	.create-button {
		padding: 11px 28px;

		border: none;
		border-radius: 5px;

		background: #2563eb;

		color: white;

		font-size: 15px;
		font-weight: 500;

		cursor: pointer;
	}

	.optional {
		font-weight: 400;
		color: #6b7280;
		font-size: 12px;
	}


	.create-button:hover {
		background: #1d4ed8;
	}


	/* ========================================
	   MOBILE
	======================================== */

	@media (max-width: 768px) {

		.page {
			margin: 24px 16px;

			padding-right: 0;
		}


		.form-grid {
			grid-template-columns: 1fr;
		}


		.full-width {
			grid-column: auto;
		}


		.actions {
			justify-content: stretch;
		}


		.create-button {
			width: 100%;
		}
	}

</style>