<script setup lang="ts">
import { useForm, Link } from '@inertiajs/vue3'

const props = defineProps<{
    aboutYou: string | null
    behavior: string | null
    commands: string | null
}>()

const form = useForm({
    about_you: props.aboutYou ?? '',
    behavior: props.behavior ?? '',
    commands: props.commands ?? ''
})

const submit = () => {
    form.post('/instructions', { preserveScroll: true })
}
</script>

<template>
    <div class="max-w-2xl mx-auto p-8 space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold">🦁 Instructions personnalisées</h1>
            <Link href="/chat" class="text-sm text-amber-600 hover:underline">
                ← Retour au chat
            </Link>
        </div>

        <!-- À propos de vous -->
        <div class="space-y-2">
            <label class="block font-medium">👤 À propos de vous</label>
            <p class="text-sm text-gray-500">
                Qui êtes-vous ? Profession, niveau, centres d'intérêt...
            </p>
            <textarea
                v-model="form.about_you"
                rows="5"
                placeholder="Ex : Je suis développeur PHP/Laravel débutant. J'aime les explications avec des analogies."
                class="w-full border dark:border-gray-700 rounded p-3 dark:bg-gray-800"
            />
        </div>

        <!-- Comportement -->
        <div class="space-y-2">
            <label class="block font-medium">🎭 Comportement de l'assistant</label>
            <p class="text-sm text-gray-500">
                Comment le lion doit-il vous répondre ? Ton, format, longueur...
            </p>
            <textarea
                v-model="form.behavior"
                rows="5"
                placeholder="Ex : Réponds de façon concise, code en premier, explique après."
                class="w-full border dark:border-gray-700 rounded p-3 dark:bg-gray-800"
            />
        </div>
        <!-- Commandes personnalisées -->
        <div class="space-y-2">
            <label class="block font-medium">⚡ Commandes personnalisées</label>
            <p class="text-sm text-gray-500">
                Une commande par ligne, au format <code>/nom = instruction</code>
            </p>
            <textarea
                v-model="form.commands"
                rows="6"
                placeholder="/eli5 = Explique le concept suivant très simplement, avec une analogie du quotidien :
/review = Analyse ce code, liste les bugs et améliorations par priorité :"
                class="w-full border dark:border-gray-700 rounded p-3 dark:bg-gray-800 font-mono text-sm"
            />
        </div>

        <div class="flex items-center gap-3">
            <button
                @click="submit"
                :disabled="form.processing"
                class="bg-amber-600 text-white px-6 py-2 rounded hover:bg-amber-700 disabled:opacity-50"
            >
                {{ form.processing ? 'Enregistrement...' : 'Enregistrer' }}
            </button>
            <span v-if="form.recentlySuccessful" class="text-green-600 text-sm">
                ✓ Enregistré !
            </span>
        </div>
    </div>
</template>
