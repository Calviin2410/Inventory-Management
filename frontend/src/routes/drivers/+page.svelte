<script>
	import { onMount } from "svelte";
	import { goto } from "$app/navigation";

	import { api } from "$lib/api.js";
	import { user } from "$lib/stores/auth.js";
	import Nav from "$lib/Nav.svelte";

	let drivers = $state([]);
	let loading = $state(true);
	let errorMessage = $state("");

	let name = $state("");
	let phone = $state("");
	let saving = $state(false);

	let showAddForm = $state(false);

	let isAdmin = $derived($user?.role === "admin");

	async function loadDrivers() {
		loading = true;
		errorMessage = "";

		try {
			const result = await api.getDrivers();

			drivers = Array.isArray(result) ? result : (result?.data ?? []);
		} catch (error) {
			errorMessage = error?.message || "Unable to load drivers.";
		} finally {
			loading = false;
		}
	}

	async function handleSubmit(event) {
		event.preventDefault();

		if (!isAdmin || saving) {
			return;
		}

		if (!name.trim()) {
			errorMessage = "Please enter the driver name.";
			return;
		}

		saving = true;
		errorMessage = "";

		try {
			const created = await api.createDriver({
				name: name.trim(),
				phone: phone.trim() || null,
			});

			drivers = [...drivers, created].sort((a, b) =>
				a.name.localeCompare(b.name),
			);

			name = "";
			phone = "";
			showAddForm = false;
		} catch (error) {
			errorMessage = error?.message || "Unable to add driver.";
		} finally {
			saving = false;
		}
	}

	onMount(async () => {
		try {
			const currentUser = await api.me();

			user.set(currentUser);

			if (currentUser?.role !== "admin") {
				goto("/dashboard");
				return;
			}

			await loadDrivers();
		} catch (error) {
			goto("/login");
		}
	});
</script>

<Nav />

<main class="app-page">
	<header class="page-heading">
		<div>
			<p class="eyebrow">TRANSPORT</p>

			<h1>Drivers</h1>

			<p>Manage drivers used for invoice deliveries.</p>
		</div>

		{#if isAdmin}
			<button
				type="button"
				class="btn btn-primary"
				onclick={() => {
					showAddForm = !showAddForm;
				}}
			>
				{showAddForm ? "Close" : "+ Add Driver"}
			</button>
		{/if}
	</header>

	{#if isAdmin && showAddForm}
		<section class="panel add-panel">
			<h2>Add Driver</h2>

			<form class="driver-form" onsubmit={handleSubmit}>
				<label>
					<span> Driver Name </span>

					<input
						type="text"
						placeholder="e.g. Bryan"
						bind:value={name}
						required
					/>
				</label>

				<label>
					<span> Phone </span>

					<input
						type="text"
						placeholder="e.g. 012-345 6789"
						bind:value={phone}
					/>
				</label>

				<div class="form-actions">
					<button
						type="button"
						class="btn"
						onclick={() => {
							showAddForm = false;

							name = "";
							phone = "";
						}}
					>
						Cancel
					</button>

					<button
						type="submit"
						class="btn btn-primary"
						disabled={saving}
					>
						{saving ? "Adding..." : "Save Driver"}
					</button>
				</div>
			</form>
		</section>
	{/if}

	{#if errorMessage}
		<div class="error-message">
			{errorMessage}
		</div>
	{/if}

	<section class="panel">
		{#if loading}
			<div class="state">Loading drivers...</div>
		{:else if drivers.length === 0}
			<div class="state">
				<h3>No drivers yet</h3>

				<p>Click Add Driver to create your first driver.</p>
			</div>
		{:else}
			<table class="data-table">
				<thead>
					<tr>
						<th> Driver </th>

						<th> Phone </th>
					</tr>
				</thead>

				<tbody>
					{#each drivers as driver (driver.id)}
						<tr>
							<td class="strong">
								{driver.name}
							</td>

							<td>
								{driver.phone || "—"}
							</td>
						</tr>
					{/each}
				</tbody>
			</table>
		{/if}
	</section>
</main>

<style>
	.add-panel {
		margin-bottom: 24px;
		padding: 24px;
	}

	.add-panel h2 {
		margin: 0 0 18px;
		font-size: 18px;
	}

	.driver-form {
		display: grid;

		grid-template-columns:
			minmax(0, 1fr)
			minmax(0, 1fr);

		gap: 16px;
	}

	label {
		display: flex;
		flex-direction: column;
		gap: 7px;
	}

	label span {
		color: #344054;
		font-size: 14px;
		font-weight: 600;
	}

	input {
		width: 100%;
		min-height: 42px;

		box-sizing: border-box;

		padding: 10px 12px;

		border: 1px solid #d0d5dd;
		border-radius: 8px;

		background: white;
		color: #101828;

		font: inherit;
	}

	input:focus {
		outline: none;

		border-color: #2563eb;

		box-shadow: 0 0 0 3px rgb(37 99 235 / 10%);
	}

	.form-actions {
		display: flex;
		justify-content: flex-end;

		gap: 10px;

		grid-column: 1 / -1;

		margin-top: 4px;
	}

	.error-message {
		margin-bottom: 20px;

		padding: 12px 14px;

		border: 1px solid #fecaca;
		border-radius: 8px;

		background: #fef2f2;
		color: #b42318;
	}

	.strong {
		font-weight: 600;
	}

	.state {
		padding: 28px;

		color: #64748b;

		text-align: center;
	}

	.state h3 {
		margin: 0 0 6px;

		color: #111827;
	}

	.state p {
		margin: 0;
	}

	@media (max-width: 760px) {
		.driver-form {
			grid-template-columns: 1fr;
		}

		.form-actions {
			grid-column: auto;
			flex-direction: column-reverse;
		}

		.form-actions .btn {
			width: 100%;
		}
	}
</style>
