<script setup lang="ts">
import { router, Link, usePage } from '@inertiajs/vue3'
import { useStream } from '@laravel/stream-vue'
import hljs from 'highlight.js'
import MarkdownIt from 'markdown-it'
import 'highlight.js/styles/github-dark.css'
import { computed, ref, watch, nextTick, onMounted } from 'vue'

const user = computed(() => usePage().props.auth.user)

const props = defineProps({
    conversations: Array,
    models: Array,
    selectedModel: String,
    currentConversation: Object,
    commands: String,
})

const useCommand = (name: string) => {
    message.value = name + ' '   // pré-remplit le champ avec la commande + un espace
}

const parsedCommands = computed(() => {
    if (!props.commands) return []

    return props.commands
        .split('\n')
        .map(line => line.trim())
        .filter(line => line.startsWith('/') && line.includes('='))
        .map(line => {
            const [name, ...rest] = line.split('=')
            
            return { name: name.trim(), instruction: rest.join('=').trim() }
        })
})

const message = ref('')
const currentModel = ref(props.selectedModel)
const liveMessages = ref([])

watch(() => props.currentConversation, (conv) => {
    liveMessages.value = conv?.messages ? [...conv.messages] : []
}, { immediate: true })

// --- URL de stream réactive ---
const streamConversationId = ref<number | null>(null)
const streamUrl = computed(() =>
    streamConversationId.value ? `/chat/${streamConversationId.value}/stream` : ''
)

const { data, isStreaming, send } = useStream(streamUrl, {
    onFinish: () => {
    if (streamedContent.value) {
        liveMessages.value.push({ role: 'assistant', content: streamedContent.value })
    }

    setTimeout(() => {
        router.visit(`/chat/${streamConversationId.value}`, {
            preserveScroll: true,
            preserveState: true,
        })
    }, 800)   // ~0,8s pour laisser le titre se générer
    },
})

const streamedContent = computed(() => {
    if (!data.value) {
        return ''
    }

    return data.value.replace(/\[REASONING\][\s\S]*?\[\/REASONING\]/g, '').trim()
})

const isPreparing = ref(false)

const submit = async () => {
    if (!message.value.trim() || isStreaming.value || isPreparing.value) return

    const myMessage = message.value
    message.value = ''
    isPreparing.value = true

    liveMessages.value.push({ role: 'user', content: myMessage })
    scrollToBottom()

    const res = await fetch('/chat/prepare', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify({
            message: myMessage,
            model: currentModel.value,
            conversation_id: props.currentConversation?.id ?? null,
        }),
    })

    if (!res.ok) {
        const text = await res.text()
        console.error('Erreur /chat/prepare:', res.status, text)
        isPreparing.value = false
        return
    }

    watch(isStreaming, (streaming) => {
    if (streaming) {
        isPreparing.value = false   // le stream a pris le relais
    }
    })

    const { conversation_id } = await res.json()

    streamConversationId.value = conversation_id
    await nextTick()
    send({})
}

const changeModel = () => {
    router.post('/chat/model', {
        model: currentModel.value,
        conversation_id: props.currentConversation?.id ?? null,
    }, { preserveScroll: true, preserveState: true })
}

const newChat = () => router.get('/chat')

const md = new MarkdownIt({
    highlight: (str, lang) => {
        if (lang && hljs.getLanguage(lang)) {
            try {
                return hljs.highlight(str, { language: lang }).value
            } catch {
                /* ignore */
            }
        }
        return ''
    },
})

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
watch(streamedContent, scrollToBottom)
watch(isStreaming, scrollToBottom)
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
                    v-model="currentModel"
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
            <!-- Liste des commandes enregistrées -->
            <div v-if="parsedCommands.length" class="px-3 py-2 border-t dark:border-gray-700">
                <p class="text-xs font-medium text-gray-400 mb-1">⚡ Commandes</p>
                <ul class="space-y-1">
                    <li
                        v-for="cmd in parsedCommands"
                        :key="cmd.name"
                        @click="useCommand(cmd.name)"
                        class="text-xs text-gray-500 cursor-pointer hover:text-amber-600"
                        :title="cmd.instruction"
                    >
                        <code class="text-amber-600">{{ cmd.name }}</code>
                    </li>
                </ul>
            </div>

            <Link href="/instructions" class="text-xs text-gray-400 hover:text-amber-600 px-3 py-2">
                ⚙️ Instructions
            </Link>

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
                <template v-if="liveMessages.length">
                    <div v-for="(m, i) in liveMessages" :key="i">
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

                <!-- Réponse EN COURS de streaming -->
                <div
                    v-if="isStreaming && streamedContent"
                    class="prose dark:prose-invert prose-slate max-w-none"
                    v-html="md.render(streamedContent)"
                />

                <!-- Loader le temps que le premier token arrive -->
                <div
                    v-if="(isPreparing || isStreaming) && !streamedContent"
                    class="flex items-center gap-2 text-gray-500 italic"
                >
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
                        v-model="message"
                        @keydown.enter.exact.prevent="submit"
                        rows="2"
                        placeholder="Écris ton message... (Entrée pour envoyer)"
                        class="flex-1 border dark:border-gray-700 rounded p-2 dark:bg-gray-800 resize-none"
                    />
                    <button
                        @click="submit"
                        :disabled="isStreaming"
                        class="bg-amber-600 text-white px-6 rounded hover:bg-amber-700 disabled:opacity-50"
                    >
                        Envoyer
                    </button>
                </div>
            </div>
        </main>
    </div>
</template>
