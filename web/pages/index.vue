<template>
	<div class="max-w-3xl mx-auto">
		<h1 class="font-bold text-4xl mt-8">PHPStan</h1>
		<div class="border-t border-2 border-gray-300 my-4"></div>
		<div class="flex flex-col space-y-4">
			<div v-if="error" class="text-red-500 p-4 border border-red-500">
				{{ error }}
			</div>
			<div class="flex flex-col space-y-4">
				<div class="text-gray-800">Code</div>
				<textarea v-model="form.code" class="border border-gray-300 p-2 rounded-md" rows="10"></textarea>
			</div>
			<div class="flex flex-col space-y-4">
				<div class="text-gray-800">Level</div>
				<select v-model="form.level" class="border border-gray-300 p-2 rounded-md">
					<option :selected="form.level === 1" value="1">1</option>
					<option :selected="form.level === 2" value="2">2</option>
					<option :selected="form.level === 3" value="3">3</option>
					<option :selected="form.level === 4" value="4">4</option>
					<option :selected="form.level === 5" value="5">5</option>
					<option :selected="form.level === 6" value="6">6</option>
					<option :selected="form.level === 7" value="7">7</option>
					<option :selected="form.level === 8" value="8">8</option>
					<option :selected="form.level === 9" value="9">9</option>
				</select>
			</div>
			<div class="text-right">
				<button @click.prevent.stop="analyse" class="bg-blue-500 border-0 text-white px-4 py-2 font-bold rounded-md hover:bg-blue-800 inline-flex items-center justify-center">
					<svg v-if="loading" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
						<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
						<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
					</svg>
					Analyse
				</button>
			</div>
		</div>
		<div v-if="result" class="space-y-4">
			<div class="border-t border-2 border-gray-300 my-4"></div>
			<div v-if="result.errors.length <= 0" class="text-blue-500 p-4 border border-blue-500">
				No errors! Awesome.
			</div>
			<div v-if="result.errors.length > 0" class="text-red-500 p-4 border border-red-500">
				Oh no! {{ result.errors.length }} error(s).
			</div>
			<div v-if="result.errors.length > 0">
				<table class="border w-full">
					<thead>
					<tr class="border bg-gray-200">
						<th class="w-20 text-left p-2">Line</th>
						<th class="text-left p-2">Error</th>
					</tr>
					</thead>
					<tbody>
					<tr v-for="error of result.errors">
						<td class="p-2">{{ error.line }}</td>
						<td class="p-2">{{ error.message }}</td>
					</tr>
					</tbody>
				</table>
			</div>
		</div>
	</div>
</template>

<script>
export default {
	data: () => ({
		loading: null,
		error: null,
		form: {
			code: "$forrest = 1;\n$forrest->run();",
			level: 9
		},
		result: null,
	}),
	methods: {
		async analyse() {
			try {
				this.loading = true;

				const res = await this.$axios.request({
					method: 'POST',
					url: '/',
					data: new URLSearchParams({
						code: this.form.code,
						level: this.form.level,
					}),
					headers: {
						'content-type': 'application/x-www-form-urlencoded'
					},
				});
				this.result = res.data;
			} catch (e) {
				console.error(e.response);

				if (e.response?.data?.error) {
					this.error = e.response?.data?.error;
				} else {
					this.error = e.message || 'Unexpected error on backend side';
				}
			} finally {
				this.loading = false;
			}
		}
	}
}
</script>

