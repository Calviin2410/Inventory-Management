import { beforeNavigate } from "$app/navigation";
import { onMount } from "svelte";

export function guardUnsaved(getDirty) {
	const message = "You have unsaved changes. Leave this page and discard them?";
	beforeNavigate((navigation) => {
		if (getDirty() && !confirm(message)) navigation.cancel();
	});
	onMount(() => {
		const handler = (event) => {
			if (!getDirty()) return;
			event.preventDefault();
			event.returnValue = "";
		};
		window.addEventListener("beforeunload", handler);
		return () => window.removeEventListener("beforeunload", handler);
	});
}
