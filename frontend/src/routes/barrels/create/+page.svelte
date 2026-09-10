<script>
	import { goto } from '$app/navigation';
	import { api } from '$lib/api.js';
	import Nav from '$lib/Nav.svelte';
	import { user } from '$lib/stores/auth.js';
	import { onMount } from 'svelte';
	let code = '';
	let saving = false;
	let errorMessage = '';

	async function handleSubmit(event) {
		event.preventDefault();

		if (saving) {
			return;
		}

		if (!code.trim()) {
			errorMessage = 'Please enter barrel code.';
			return;
		}

		saving = true;
		errorMessage = '';

		try {
			console.log(
				'Creating barrel:',
				code
			);

			const result =
				await api.createBarrel({
					code: code.trim()
				});

			console.log(
				'Created barrel:',
				result
			);

			goto('/barrels');

		} catch (error) {
			console.error(
				'Create barrel error:',
				error
			);

			errorMessage =
				error?.message ??
				'Unable to create barrel.';
		} finally {
			saving = false;
		}
	}
</script>

<Nav />

<div class="page">

	<h1>Add Barrel</h1>

	<form
		class="form-card"
		onsubmit={handleSubmit}
	>

		<div class="form-group">
			<label for="code">
				Barrel Code
			</label>

			<input
				id="code"
				type="text"
				placeholder="e.g. 001"
				bind:value={code}
				required
			/>
		</div>

		{#if errorMessage}
			<p class="error-message">
				{errorMessage}
			</p>
		{/if}

		<div class="actions">

			<button
				type="button"
				class="cancel-button"
				onclick={() => goto('/barrels')}
			>
				Cancel
			</button>

			<button
				type="submit"
				class="create-button"
				disabled={saving}
			>
				{saving ? 'Adding...' : 'Add Barrel'}
			</button>

		</div>

	</form>

</div>

<style>
	.page {
		margin: 36px 48px;
		max-width: 700px;

		font-family: Arial, Helvetica, sans-serif;
	}

	h1 {
		margin-bottom: 28px;

		font-size: 34px;
	}


	.form-card {
		padding: 25px;

		border: 1px solid #e5e7eb;
		border-radius: 8px;

		background: white;
	}


	.form-group {
		display: flex;
		flex-direction: column;

		gap: 7px;

		margin-bottom: 20px;
	}

	label {
		font-size: 14px;
		font-weight: 600;
	}

	input {
		padding: 11px 12px;

		border: 1px solid #d1d5db;
		border-radius: 6px;

		font-size: 14px;
	}

	.actions {
		display: flex;

		justify-content: flex-end;

		gap: 10px;

		margin-top: 25px;
	}


	.cancel-button,
	.create-button {
		padding: 10px 22px;

		border-radius: 6px;

		cursor: pointer;
	}


	.cancel-button {
		border: 1px solid #d1d5db;

		background: white;
	}


	.create-button {
		border: none;

		background: #2563eb;

		color: white;
	}
</style>