<script>
    import { onMount } from "svelte";
    import { goto } from "$app/navigation";
    import { page } from "$app/state";

    import { api } from "$lib/api.js";
    import Nav from "$lib/Nav.svelte";

    let plateNumber = $state("");
    let status = $state("available");

    let loading = $state(true);
    let saving = $state(false);
    let errorMessage = $state("");

    const vehicleId = $derived(page.params.id);

    async function loadVehicle() {
        loading = true;
        errorMessage = "";

        try {
            const vehicle = await api.getVehicle(vehicleId);

            plateNumber = vehicle?.plate_number ?? "";

            status = vehicle?.status ?? "available";
        } catch (error) {
            errorMessage =
                error instanceof Error
                    ? error.message
                    : "Unable to load vehicle";
        } finally {
            loading = false;
        }
    }

    async function saveVehicle(event) {
        event.preventDefault();

        if (!plateNumber.trim()) {
            errorMessage = "Plate number is required.";
            return;
        }

        saving = true;
        errorMessage = "";

        try {
            await api.updateVehicle(vehicleId, {
                plate_number: plateNumber.trim(),
                status,
            });

            goto("/vehicles");
        } catch (error) {
            errorMessage =
                error instanceof Error
                    ? error.message
                    : "Unable to update vehicle";
        } finally {
            saving = false;
        }
    }

    onMount(loadVehicle);
</script>

<Nav />

<main class="app-page">
    <header class="page-heading">
        <div>
            <p class="eyebrow">VEHICLES</p>

            <h1>Edit Vehicle</h1>

            <p>Update the vehicle plate number and availability status.</p>
        </div>

        <button class="btn" onclick={() => goto("/vehicles")}> Back </button>
    </header>

    {#if loading}
        <div class="state">Loading vehicle...</div>
    {:else}
        <section class="form-card">
            <form onsubmit={saveVehicle}>
                {#if errorMessage}
                    <div class="error-message">
                        {errorMessage}
                    </div>
                {/if}

                <div class="field">
                    <label for="plate-number"> Plate Number </label>

                    <input
                        id="plate-number"
                        class="control"
                        type="text"
                        bind:value={plateNumber}
                        placeholder="Example: VNN 8113"
                    />
                </div>

                <div class="field">
                    <label for="status"> Status </label>

                    <select id="status" class="control" bind:value={status}>
                        <option value="available"> Available </option>

                        <option value="maintenance"> Maintenance </option>
                    </select>
                </div>

                <div class="form-actions">
                    <button
                        type="button"
                        class="btn"
                        onclick={() => goto("/vehicles")}
                    >
                        Cancel
                    </button>

                    <button
                        type="submit"
                        class="btn btn-primary"
                        disabled={saving}
                    >
                        {saving ? "Saving..." : "Save Changes"}
                    </button>
                </div>
            </form>
        </section>
    {/if}
</main>

<style>
    .form-card {
        max-width: 650px;
        padding: 28px;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        background: white;
    }

    form {
        display: flex;
        flex-direction: column;
        gap: 22px;
    }

    .field {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .field label {
        color: #374151;
        font-size: 14px;
        font-weight: 600;
    }

    .field .control {
        width: 100%;
    }

    .form-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        padding-top: 8px;
    }

    .error-message {
        padding: 12px 14px;
        border: 1px solid #fecaca;
        border-radius: 8px;
        background: #fef2f2;
        color: #b42318;
        font-size: 14px;
    }

    .state {
        padding: 30px 0;
        color: #64748b;
    }

    @media (max-width: 760px) {
        .form-card {
            padding: 20px;
        }

        .form-actions {
            flex-direction: column-reverse;
        }

        .form-actions .btn {
            width: 100%;
        }
    }
</style>
