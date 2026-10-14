<script>
	import '../app.css';

	import { onMount } from 'svelte';
	import { browser } from '$app/environment';

	import { api, getToken, setToken } from '$lib/api.js';
	import { user } from '$lib/stores/auth.js';
	let { children } = $props();

	onMount(() => {
		function closeDetailsOnOutsideClick(event) {
			const target = event.target;
			if (!(target instanceof Element)) return;

			document.querySelectorAll("details[open]").forEach((details) => {
				if (!details.contains(target)) details.removeAttribute("open");
			});
		}

		function closeDetailsOnEscape(event) {
			if (event.key !== "Escape") return;
			document.querySelectorAll("details[open]").forEach((details) => {
				details.removeAttribute("open");
			});
		}

		document.addEventListener("click", closeDetailsOnOutsideClick);
		document.addEventListener("keydown", closeDetailsOnEscape);

		return () => {
			document.removeEventListener("click", closeDetailsOnOutsideClick);
			document.removeEventListener("keydown", closeDetailsOnEscape);
		};
	});

	onMount(async () => {
		if (!browser) {
			return;
		}

		const savedToken = getToken();

		if (!savedToken) {
			user.set(null);
			return;
		}

		try {
			const currentUser =
				await api.me();

			user.set(currentUser);
		} catch (error) {
			console.error(
				'Unable to restore user:',
				error
			);

			setToken(null);
			user.set(null);
		}
	});
</script>

{@render children()}
