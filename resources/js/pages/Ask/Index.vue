<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import hljs from 'highlight.js'
import MarkdownIt from 'markdown-it'
import { ask } from '@/actions/App/Http/Controllers/AskController'
import 'highlight.js/styles/github-dark.css'

// 1. Les props envoyées par le controller (Inertia::render)
const props = defineProps({
    models: Array<{ id: string; name: string }>,
    selectedModel: String,
    message: String,
    response: String,
    error: String,
})

// 2. Le formulaire : ses champs correspondent à la validation du controller
const form = useForm({
    message: props.message ?? '',
    model: props.selectedModel,
})

// 3. L'envoi : POST vers la route ask.post
const submit = () => {
    form.submit(ask())
}

// 4. Le moteur Markdown + coloration syntaxique
const md = new MarkdownIt({
    highlight: function (str, lang) {
        if (lang && hljs.getLanguage(lang)) {
            try {
                return hljs.highlight(str, { language: lang }).value
            } catch {}
        }

        return ''
    }
})
</script>

<template>
    <div class="max-w-3xl mx-auto p-6 space-y-6">
        <h1 class="text-2xl font-bold">🦁 Mini-GPT Lion</h1>

        <!-- Le formulaire -->
        <div class="space-y-4">
            <select
                v-model="form.model"
                class="w-full border rounded p-2"
            >
                <option
                    v-for="model in models"
                    :key="model.id"
                    :value="model.id"
                >
                    {{ model.name }}
                </option>
            </select>

            <textarea
                v-model="form.message"
                rows="4"
                placeholder="Pose ta question au lion..."
                class="w-full border rounded p-2"
            />

            <button
                @click="submit"
                :disabled="form.processing"
                class="bg-amber-600 text-white px-4 py-2 rounded disabled:opacity-50"
            >
                {{ form.processing ? 'Le lion réfléchit...' : 'Envoyer' }}
            </button>
        </div>

        <!-- L'erreur, si elle existe -->
        <div v-if="props.error" class="text-red-500 p-4 rounded bg-red-50">
            Erreur : {{ props.error }}
        </div>

        <!-- La réponse, rendue en Markdown -->
        <div
            v-if="props.response"
            class="prose dark:prose-invert prose-slate max-w-none"
            v-html="md.render(props.response)"
        />
    </div>
</template>
