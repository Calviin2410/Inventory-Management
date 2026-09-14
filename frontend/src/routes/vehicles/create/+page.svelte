<script>
    import { goto } from "$app/navigation";
    import { api } from "$lib/api.js";
    import Nav from "$lib/Nav.svelte";
    import { guardUnsaved } from "$lib/unsaved.js";

    let plateNumber = $state("");
    let loading = $state(false);
    let errorMessage = $state("");
    let formDirty = $state(false);
    guardUnsaved(() => formDirty);

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

            formDirty = false;
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

<main class="app-page create-page">
    <header class="page-heading">
        <div>
            <p class="eyebrow">VEHICLES</p>
            <h1>Add vehicle</h1>
            <p class="subtitle">Register a delivery vehicle and its plate number.</p>
        </div>
    </header>

    <section class="form-card" oninput={() => formDirty = true}>
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
                onclick={() => goto("/vehicles")}
            >
                Cancel
            </button>

            <button
                type="button"
                class="submit-button"
                disabled={loading}
                onclick={handleSubmit}
            >
                {loading ? "Adding…" : "Add vehicle"}
            </button>
        </div>
    </section>
</main>

<style>
    .create-page { max-width: 980px; }

    .form-card {
        max-width: 680px;
        padding: 24px;

        border: 1px solid #e5e7eb;
        border-radius: 9px;

        background: white;
        box-shadow: 0 2px 8px rgb(15 23 42 / 3%);
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
    @media (max-width: 560px) {
        .form-card { padding: 20px; }
        .actions { display: grid; grid-template-columns: 1fr 1fr; }
        .actions button { width: 100%; }
    }
</style>
