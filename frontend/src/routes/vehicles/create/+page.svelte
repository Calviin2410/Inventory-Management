<script>
    import { goto } from "$app/navigation";
    import { api } from "$lib/api.js";
    import Nav from "$lib/Nav.svelte";

    let plateNumber = $state("");
    let loading = $state(false);
    let errorMessage = $state("");

    async function handleSubmit() {
        errorMessage = "";

        if (!plateNumber.trim()) {
            errorMessage = "Please enter plate number";
            return;
        }

        loading = true;

        try {
            await api.createVehicle({
                plate_number: plateNumber.trim(),
            });

            goto("/vehicles");
        } catch (error) {
            errorMessage =
                error instanceof Error
                    ? error.message
                    : "Unable to create vehicle";
        } finally {
            loading = false;
        }
    }
</script>

<Nav />

<main class="page">
    <header class="page-header">
        <div>
            <p class="eyebrow">VEHICLES</p>
            <h1>Add Vehicle</h1>
            <p class="subtitle">Add a new vehicle.</p>
        </div>
    </header>

    <section class="form-card">
        {#if errorMessage}
            <div class="error">
                {errorMessage}
            </div>
        {/if}

        <div class="form-group">
            <label for="plateNumber"> Plate Number </label>

            <input
                id="plateNumber"
                type="text"
                placeholder="Example: JQK 1234"
                bind:value={plateNumber}
            />
        </div>

        <div class="actions">
            <button
                type="button"
                class="cancel-button"
                on:click={() => goto("/vehicles")}
            >
                Cancel
            </button>

            <button
                type="button"
                class="submit-button"
                disabled={loading}
                on:click={handleSubmit}
            >
                {loading ? "Adding..." : "Add Vehicle"}
            </button>
        </div>
    </section>
</main>

<style>
    .page {
        margin: 36px 48px 60px 48px;
        max-width: 720px;

        font-family: Arial, Helvetica, sans-serif;

        color: #111827;
    }

    .page-header {
        margin-bottom: 28px;
    }

    .eyebrow {
        margin: 0;

        color: #64748b;

        font-size: 12px;
        font-weight: 700;

        letter-spacing: 0.1em;
    }

    h1 {
        margin: 5px 0 6px 0;

        font-size: 34px;
    }

    .subtitle {
        margin: 0;

        color: #64748b;

        font-size: 14px;
    }

    .form-card {
        padding: 24px;

        border: 1px solid #e5e7eb;
        border-radius: 9px;

        background: white;
    }

    .form-group {
        display: flex;
        flex-direction: column;

        gap: 8px;
    }

    label {
        color: #374151;

        font-size: 14px;
        font-weight: 600;
    }

    input {
        height: 44px;

        box-sizing: border-box;

        padding: 0 13px;

        border: 1px solid #d1d5db;
        border-radius: 7px;

        font-size: 14px;

        outline: none;
    }

    input:focus {
        border-color: #315ee7;

        box-shadow: 0 0 0 2px rgba(49, 94, 231, 0.1);
    }

    .actions {
        display: flex;
        justify-content: flex-end;

        gap: 10px;

        margin-top: 24px;
    }

    .cancel-button,
    .submit-button {
        height: 42px;

        padding: 0 18px;

        border-radius: 7px;

        font-size: 14px;
        font-weight: 600;

        cursor: pointer;
    }

    .cancel-button {
        border: 1px solid #d1d5db;

        background: white;

        color: #374151;
    }

    .submit-button {
        border: none;

        background: #315ee7;

        color: white;
    }

    .submit-button:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    .error {
        margin-bottom: 18px;

        padding: 11px 13px;

        border-radius: 7px;

        background: #fef2f2;
        color: #dc2626;

        font-size: 13px;
    }
</style>
