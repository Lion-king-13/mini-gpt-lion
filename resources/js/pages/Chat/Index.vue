<script setup lang="ts">
import { router, useForm, Link, usePage } from '@inertiajs/vue3'
import hljs from 'highlight.js'
import MarkdownIt from 'markdown-it'
import 'highlight.js/styles/github-dark.css'
import { computed, ref, watch, nextTick, onMounted } from 'vue'

const user = computed(() => usePage().props.auth.user)

const props = defineProps({
    conversations: Array,
    models: Array,
    selectedModel: String,
    currentConversation: Object,   // null quand on est sur l'accueil
})

// Le formulaire d'envoi de message
const form = useForm({
    message: '',
    model: props.selectedModel,
    conversation_id: props.currentConversation?.id ?? null,
})

const submit = () => {
    if (!form.message.trim()) {
return
}          // on n'envoie pas du vide

    form.post('/chat', {
        preserveScroll: true,
        onSuccess: () => form.reset('message'), // on vide le champ après envoi
    })
}

// Changement de modèle → sauvegarde côté serveur (users + conversation)
const changeModel = () => {
    router.post('/chat/model', {
        model: form.model,
        conversation_id: props.currentConversation?.id ?? null,
    }, { preserveScroll: true, preserveState: true })
}

const newChat = () => router.get('/chat')

// Markdown + coloration syntaxique (repris de l'exercice 1)
const md = new MarkdownIt({
    highlight: (str, lang) => {
        if (lang && hljs.getLanguage(lang)) {
            try {
 return hljs.highlight(str, { language: lang }).value 
} catch {}
        }

        return ''
    },
})

// Auto-scroll vers le bas
const messagesContainer = ref(null)

const scrollToBottom = () => {
    nextTick(() => {
        const el = messagesContainer.value

        if (el) {
el.scrollTop = el.scrollHeight
}
    })
}

onMounted(scrollToBottom)
watch(() => props.currentConversation, scrollToBottom)
watch(() => form.processing, scrollToBottom)

</script>

<template>
    <div class="flex h-screen bg-white dark:bg-gray-900">
        <!-- ===== SIDEBAR ===== -->
        <aside class="w-64 border-r dark:border-gray-700 flex flex-col">
            <div class="p-4 space-y-3">
                <button
                    @click="newChat"
                    class="w-full bg-amber-600 text-white py-2 rounded hover:bg-amber-700"
                >
                    + Nouvelle conversation
                </button>

                <select
                    v-model="form.model"
                    @change="changeModel"
                    class="w-full border dark:border-gray-700 rounded p-2 text-sm dark:bg-gray-800"
                >
                    <option v-for="m in models" :key="m.id" :value="m.id">
                        {{ m.name }}
                    </option>
                </select>
            </div>

            <!-- Liste des conversations -->
            <nav class="flex-1 overflow-y-auto px-2 space-y-1">
                <Link
                    v-for="c in conversations"
                    :key="c.id"
                    :href="`/chat/${c.id}`"
                    class="block px-3 py-2 rounded text-sm truncate"
                    :class="c.id === currentConversation?.id
                        ? 'bg-amber-100 dark:bg-amber-900 font-medium'
                        : 'hover:bg-gray-100 dark:hover:bg-gray-800'"
                >
                    {{ c.title ?? 'Sans titre' }}
                </Link>
            </nav>
            <!-- Bloc compte en bas de la sidebar -->
            <div class="border-t dark:border-gray-700 p-3 flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-amber-600 text-white flex items-center justify-center text-sm font-medium">
                    {{ user?.name?.charAt(0) ?? '?' }}
                </div>
                <span class="flex-1 text-sm truncate">{{ user?.name }}</span>
                <Link
                    href="/logout"
                    method="post"
                    as="button"
                    class="text-xs text-gray-400 hover:text-red-500"
                >
                    Déconnexion
                </Link>
            </div>
        </aside>

        <!-- ===== ZONE PRINCIPALE ===== -->
        <main class="flex-1 flex flex-col">
            <!-- Les messages -->
            <div ref="messagesContainer" class="flex-1 overflow-y-auto p-6 space-y-4">
                <template v-if="currentConversation">
                    <div
                        v-for="(m, i) in currentConversation.messages"
                        :key="i"
                    >
                        <!-- Message de l'utilisateur -->
                        <div
                            v-if="m.role === 'user'"
                            class="ml-auto max-w-2xl bg-amber-600 text-white px-4 py-2 rounded-lg w-fit"
                        >
                            {{ m.content }}
                        </div>
                        <!-- Réponse du lion 🦁 -->
                        <div
                            v-else
                            class="prose dark:prose-invert prose-slate max-w-none"
                            v-html="md.render(m.content)"
                        />
                    </div>
                </template>

                <div v-else class="text-center text-gray-400 mt-20">
                    🦁 Pose ta première question au lion
                </div>

                <!-- Loader animé avec des lions -->
                <div v-if="form.processing" class="flex items-center gap-2 text-gray-500 italic">
                    <span>Le lion réfléchit</span>
                    <span class="flex gap-1">
                        <span class="animate-bounce" style="animation-delay: 0ms">🦁</span>
                        <span class="animate-bounce" style="animation-delay: 150ms">🦁</span>
                        <span class="animate-bounce" style="animation-delay: 300ms">🦁</span>
                    </span>
                </div>
            </div>

            <!-- Le champ de saisie -->
            <div class="border-t dark:border-gray-700 p-4">
                <div class="flex gap-2 max-w-3xl mx-auto">
                    <textarea
                        v-model="form.message"
                        @keydown.enter.exact.prevent="submit"
                        rows="2"
                        placeholder="Écris ton message... (Entrée pour envoyer)"
                        class="flex-1 border dark:border-gray-700 rounded p-2 dark:bg-gray-800 resize-none"
                    />
                    <button
                        @click="submit"
                        :disabled="form.processing"
                        class="bg-amber-600 text-white px-6 rounded hover:bg-amber-700 disabled:opacity-50"
                    >
                        Envoyer
                    </button>
                </div>
            </div>
        </main>
    </div>
</template>
