<script>
	import { goto } from '$app/navigation';
	import { onMount } from 'svelte';
	import { api } from '$lib/api.js';
	import Nav from '$lib/Nav.svelte';


	let invoiceId = $state('');
	let customerName = $state('');

	let phone = $state('');

	let rentalStart = $state(new Date().toISOString().slice(0, 10));
	let rentalEnd = $state('');
	let address = $state('');
	let barrelId = $state('');
	let description = $state('');
	let availableBarrels = $state([]);
	let isLoading = $state(true);
	let isSubmitting = $state(false);
	let pageError = $state('');

	async function loadFormData() {
		isLoading = true;
		pageError = '';

		try {

			const [numberResult, barrelsResult] = await Promise.all([
				api.getNextInvoiceNo(),
				api.getBarrels()
			]);
			invoiceId = numberResult.invoice_no;
			availableBarrels = barrelsResult.filter((item) => item.status === 'available');
		} catch (error) {
			console.error('Failed to load invoice form:', error);
			pageError = 'We could not load the invoice details. Please refresh and try again.';
		} finally {
			isLoading = false;
		}
	}

	async function findOrCreateCustomer() {
		const name = customerName.trim();
			if (name) {
			const customers = await api.getCustomers({ search: name });
			const existingCustomer = customers.find(
				(item) => item.name?.toLowerCase() === name.toLowerCase()
			);

			if (existingCustomer) return existingCustomer;
		}
		return api.createCustomer({
			name: name || null,
			phone: phone.trim() || null
		});
	}

	async function handleSubmit() {
			pageError = '';

			if (!barrelId) {
				pageError = 'Please select a barrel for this invoice.';
				return;
			}

			if (!rentalStart) {
				pageError = 'Please select a rental start date.';
				return;
			}

			if (rentalEnd && rentalEnd < rentalStart) {
				pageError = 'Rental end cannot be earlier than the start date.';
				return;
			}

			isSubmitting = true;

			try {
				const customer = await findOrCreateCustomer();
				await api.createInvoice({
				customer_id: customer.id,
				issued_date: rentalStart,
				address: address.trim() || null,
				notes: null,
				items: [
					{
						barrel_id: Number(barrelId),
						description: description.trim() || null,

						rental_start: rentalStart,
						rental_end: rentalEnd || null
					}
				]
			});

			goto('/invoices');
		} catch (error) {
			console.error('Create invoice failed:', error);
			pageError = error instanceof Error ? error.message : 'Failed to create invoice.';
		} finally {
			isSubmitting = false;
		}
	}

    onMount(loadFormData);
</script>

<Nav />

<main class="page-shell">
	<header class="page-header">
		<div>
			<p class="eyebrow">INVOICES</p>
			<h1>Create invoice</h1>
			<p class="subtitle">Record a new barrel rental and customer details.</p>
		</div>
		<div class="invoice-number">
			<span>Invoice number</span>
			<strong>{invoiceId || 'Loading…'}</strong>
		</div>
	</header>


	{#if pageError}
		<div class="alert" role="alert">{pageError}</div>
	{/if}

	<form onsubmit={(event) => { event.preventDefault(); handleSubmit(); }}>
		<section class="card">
			<div class="card-heading">
				<div class="step">1</div>
				<div>
					<h2>Customer details</h2>
					<p>Name and contact information can be added later.</p>
				</div>
			</div>

			<div class="form-grid">	
				<label>
					<span>Customer name <small>Optional</small></span>
					<input type="text" placeholder="e.g. Alex Tan" bind:value={customerName} />
				</label>

				<label>
					<span>Phone <small>Optional</small></span>
					<input type="tel" placeholder="e.g. 012-345 6789" bind:value={phone} />
				</label>

				<label class="full-width">
					<span>Address <small>Optional</small></span>
					<textarea rows="3" placeholder="Enter the delivery or billing address" bind:value={address}></textarea>
				</label>
			</div>
		</section>

		<section class="card">
			<div class="card-heading">
				<div class="step">2</div>
				<div>
					<h2>Rental details</h2>
					<p>Select the barrel and rental period for this invoice.</p>
				</div>
			</div>
			<div class="form-grid">
				<label class="full-width">
					<span>Barrel <b>Required</b></span>
					<select bind:value={barrelId} disabled={isLoading} required>
						<option value="">{isLoading ? 'Loading available barrels…' : 'Select an available barrel'}</option>
						{#each availableBarrels as barrel (barrel.id)}
							<option value={barrel.id}>{barrel.code}{barrel.type ? ` · ${barrel.type}` : ''}</option>
						{/each}
					</select>
					{#if !isLoading && availableBarrels.length === 0}
						<em>No barrels are currently available.</em>
					{/if}
				</label>
				<label>
					<span>Rental start <b>Required</b></span>
					<input type="date" bind:value={rentalStart} required />
				</label>
				<label>
					<span>Rental end <b>Required</b></span>

					<input
						type="date"
						min={rentalStart}
						bind:value={rentalEnd}
						required
					/>
				</label>
				<label class="full-width">
					<span>Description <small>Optional</small></span>
					<textarea rows="3" placeholder="Add notes about the barrel or rental" bind:value={description}></textarea>
				</label>
			</div>
		</section>

		<div class="actions">
			<button class="secondary" type="button" onclick={() => goto('/invoices')}>Cancel</button>
			<button class="primary" type="submit" disabled={isSubmitting || isLoading || availableBarrels.length === 0}>
			{isSubmitting ? 'Creating invoice…' : 'Create invoice'}
			</button>
		</div>
	</form>
</main>

<style>
:global(body) { background: #f6f8fb; }
	.page-shell { max-width: 980px; margin: 0 auto; padding: 44px 28px 72px; font-family: Inter, ui-sans-serif, system-ui, sans-serif; color: #172033; }
	.page-header { display: flex; align-items: flex-end; justify-content: space-between; gap: 24px; margin-bottom: 30px; }
	.eyebrow { margin: 0 0 7px; color: #3563e9; font-size: 12px; font-weight: 800; letter-spacing: .12em; }
	h1 { margin: 0; font-size: 34px; letter-spacing: -.035em; }
	.subtitle { margin: 8px 0 0; color: #697386; font-size: 15px; }
	.invoice-number { min-width: 150px; padding: 14px 18px; border: 1px solid #dfe5ef; border-radius: 12px; background: #fff; text-align: right; box-shadow: 0 3px 12px rgb(30 52 90 / 5%); }
	.invoice-number span { display: block; margin-bottom: 4px; color: #7a8496; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; }
	.invoice-number strong { color: #25314d; font-size: 17px; }
	.alert { margin-bottom: 20px; padding: 13px 16px; border: 1px solid #fecaca; border-radius: 10px; background: #fef2f2; color: #b42318; font-size: 14px; }
	.card { margin-bottom: 22px; overflow: hidden; border: 1px solid #dfe5ef; border-radius: 14px; background: #fff; box-shadow: 0 5px 22px rgb(30 52 90 / 5%); }
	.card-heading { display: flex; align-items: center; gap: 13px; padding: 20px 24px; border-bottom: 1px solid #e8ecf2; background: #fbfcfe; }
	.step { display: grid; width: 30px; height: 30px; place-items: center; border-radius: 9px; background: #e9efff; color: #3563e9; font-size: 14px; font-weight: 800; }
	h2 { margin: 0; font-size: 17px; }
	.card-heading p { margin: 3px 0 0; color: #7a8496; font-size: 13px; }
	.form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 22px; padding: 25px; }
	label { display: flex; flex-direction: column; gap: 8px; color: #344054; font-size: 13px; font-weight: 700; }
	label span { display: flex; align-items: center; gap: 7px; }
	small, b { padding: 2px 7px; border-radius: 20px; font-size: 10px; font-weight: 700; }
	small { background: #f1f3f7; color: #7a8496; }
	b { background: #eaf8f1; color: #16805c; }
	input, textarea, select { width: 100%; box-sizing: border-box; padding: 11px 12px; border: 1px solid #ccd4e0; border-radius: 9px; outline: none; background: #fff; color: #172033; font: inherit; font-weight: 400; transition: border-color .15s, box-shadow .15s; }
	input:focus, textarea:focus, select:focus { border-color: #4771e8; box-shadow: 0 0 0 3px rgb(53 99 233 / 12%); }
	textarea { resize: vertical; }
	.full-width { grid-column: 1 / -1; }
	em { color: #b54708; font-size: 12px; font-weight: 500; }
	.actions { display: flex; justify-content: flex-end; gap: 12px; padding-top: 4px; }
	button { padding: 11px 21px; border-radius: 9px; font: inherit; font-size: 14px; font-weight: 700; cursor: pointer; }
	.secondary { border: 1px solid #ccd4e0; background: #fff; color: #344054; }
	.primary { border: 1px solid #3563e9; background: #3563e9; color: #fff; box-shadow: 0 4px 10px rgb(53 99 233 / 22%); }
	button:hover:not(:disabled) { filter: brightness(.97); }
	button:disabled { cursor: not-allowed; opacity: .55; }
	@media (max-width: 640px) {
		.page-shell { padding: 28px 16px 50px; }
		.page-header { align-items: stretch; flex-direction: column; }
		.invoice-number { text-align: left; }
		.form-grid { grid-template-columns: 1fr; padding: 20px; }
		.full-width { grid-column: auto; }
		.actions button { flex: 1; }
	}
</style>