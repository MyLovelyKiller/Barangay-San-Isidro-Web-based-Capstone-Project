/* ============================================================
   Barangay Assistant chatbot — text + voice (speech-to-text
   input via Web Speech API, spoken replies via speechSynthesis)
   ============================================================ */

(function () {
    "use strict";

    // ---- element refs ----
    const chatbot = document.getElementById("chatbot");
    const chatBody = document.getElementById("chat-body");
    const userMessageInput = document.getElementById("userMessage");
    const micBtn = document.getElementById("micBtn");
    const speakerToggle = document.getElementById("speakerToggle");
    const langButtons = document.querySelectorAll(".lang-btn");

    // ---- browser feature detection ----
    const SpeechRecognitionCtor = window.SpeechRecognition || window.webkitSpeechRecognition;
    const hasTTS = "speechSynthesis" in window;

    // ---- persisted preferences ----
    let voiceEnabled = hasTTS && localStorage.getItem("brgy_voice_enabled") !== "false";
    let recognitionLang = localStorage.getItem("brgy_voice_lang") || "fil-PH";

    let activeRecognition = null;
    let isListening = false;

    /* ---------------- helpers ---------------- */

    function escapeHTML(str) {
        const div = document.createElement("div");
        div.textContent = str;
        return div.innerHTML;
    }

    function stripHTML(html) {
        const div = document.createElement("div");
        div.innerHTML = html;
        return (div.textContent || div.innerText || "").replace(/\s+/g, " ").trim();
    }

    function scrollToBottom() {
        chatBody.scrollTop = chatBody.scrollHeight;
    }

    function appendUserMessage(text) {
        const div = document.createElement("div");
        div.className = "user-msg";
        div.innerHTML = escapeHTML(text);
        chatBody.appendChild(div);
        scrollToBottom();
    }

    // Bot answers come from our own FAQ database (chatbot.php) and may
    // legitimately contain simple markup like <br> for formatting, so
    // they're inserted as-is rather than escaped.
    function appendBotMessage(html) {
        const row = document.createElement("div");
        row.className = "bot-row";
        row.innerHTML =
            '<div class="chat-avatar small" aria-hidden="true">B</div>' +
            '<div class="bot-msg">' + html + "</div>";
        chatBody.appendChild(row);
        scrollToBottom();
    }

    function appendNotice(text) {
        const div = document.createElement("div");
        div.className = "chat-notice";
        div.textContent = text;
        chatBody.appendChild(div);
        scrollToBottom();
    }

    function showTyping() {
        const row = document.createElement("div");
        row.className = "bot-row typing-row";
        row.innerHTML =
            '<div class="chat-avatar small" aria-hidden="true">B</div>' +
            '<div class="bot-msg"><span class="typing-dots"><span></span><span></span><span></span></span></div>';
        chatBody.appendChild(row);
        scrollToBottom();
        return row;
    }

    /* ---------------- chat open/close ---------------- */

    window.toggleChat = function toggleChat() {
        const isOpen = chatbot.style.display === "flex";
        if (isOpen) {
            chatbot.style.display = "none";
            chatbot.classList.remove("open");
        } else {
            chatbot.style.display = "flex";
            chatbot.classList.add("open");
            userMessageInput.focus();
        }
    };

    /* ---------------- FAQ shortcut buttons ---------------- */

    window.askFAQ = function askFAQ(question) {
        userMessageInput.value = question;
        sendMessage();
    };

    /* ---------------- sending messages ---------------- */

    window.sendMessage = function sendMessage() {
        const message = userMessageInput.value.trim();
        if (message === "") return;

        appendUserMessage(message);
        const typingRow = showTyping();
        userMessageInput.value = "";

        fetch("/BMS/CHATBOT/chatbot.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: "message=" + encodeURIComponent(message),
        })
            .then((res) => res.text())
            .then((data) => {
                typingRow.remove();
                appendBotMessage(data);
                speak(data);
            })
            .catch(() => {
                typingRow.remove();
                appendNotice("Connection problem. Please try again.");
            });
    };

    userMessageInput.addEventListener("keydown", function (e) {
        if (e.key === "Enter") {
            e.preventDefault();
            sendMessage();
        }
    });

    /* ---------------- text-to-speech ---------------- */

    function pickVoice(lang) {
        if (!hasTTS) return null;
        const voices = window.speechSynthesis.getVoices();
        if (!voices.length) return null;
        const primary = lang.split("-")[0]; // "fil" or "en"
        // Filipino has very little OS/browser voice coverage — also try "tl"
        const altPrimary = primary === "fil" ? "tl" : null;

        return (
            voices.find((v) => v.lang.toLowerCase().startsWith(primary)) ||
            (altPrimary && voices.find((v) => v.lang.toLowerCase().startsWith(altPrimary))) ||
            voices.find((v) => v.lang.toLowerCase().startsWith("en")) ||
            null
        );
    }

    function speak(rawHtml) {
        if (!voiceEnabled || !hasTTS) return;
        const text = stripHTML(rawHtml);
        if (!text) return;

        window.speechSynthesis.cancel();
        const utt = new SpeechSynthesisUtterance(text);
        const voice = pickVoice(recognitionLang);
        if (voice) {
            utt.voice = voice;
            utt.lang = voice.lang;
        } else {
            utt.lang = recognitionLang;
        }
        utt.onstart = () => micBtn.classList.add("speaking");
        utt.onend = () => micBtn.classList.remove("speaking");
        utt.onerror = () => micBtn.classList.remove("speaking");
        window.speechSynthesis.speak(utt);
    }

    if (speakerToggle) {
        if (!hasTTS) {
            speakerToggle.style.display = "none";
        } else {
            speakerToggle.setAttribute("aria-pressed", String(voiceEnabled));
            speakerToggle.textContent = voiceEnabled ? "🔊" : "🔇";
            speakerToggle.addEventListener("click", () => {
                voiceEnabled = !voiceEnabled;
                localStorage.setItem("brgy_voice_enabled", String(voiceEnabled));
                speakerToggle.setAttribute("aria-pressed", String(voiceEnabled));
                speakerToggle.textContent = voiceEnabled ? "🔊" : "🔇";
                if (!voiceEnabled) window.speechSynthesis.cancel();
            });
        }
    }

    /* ---------------- language switch ---------------- */

    langButtons.forEach((btn) => {
        if (btn.dataset.lang === recognitionLang) btn.classList.add("active");
        else btn.classList.remove("active");

        btn.addEventListener("click", () => {
            recognitionLang = btn.dataset.lang;
            localStorage.setItem("brgy_voice_lang", recognitionLang);
            langButtons.forEach((b) => b.classList.toggle("active", b === btn));
        });
    });

    /* ---------------- speech-to-text (mic button) ---------------- */

    if (!SpeechRecognitionCtor) {
        if (micBtn) {
            micBtn.classList.add("unsupported");
            micBtn.title = "Voice input isn't supported in this browser";
        }
    } else if (micBtn) {
        micBtn.addEventListener("click", () => {
            if (isListening) {
                if (activeRecognition) activeRecognition.stop();
                return;
            }
            startListening();
        });
    }

    function startListening() {
        // Barge-in: if the assistant is currently talking, let the tap interrupt it
        if (hasTTS) window.speechSynthesis.cancel();

        const recognition = new SpeechRecognitionCtor();
        recognition.lang = recognitionLang;
        recognition.continuous = false;
        recognition.interimResults = true;

        recognition.onstart = () => {
            isListening = true;
            micBtn.classList.add("listening");
            micBtn.classList.remove("speaking");
        };

        recognition.onresult = (event) => {
            let interim = "";
            let final = "";
            for (let i = event.resultIndex; i < event.results.length; i++) {
                const transcript = event.results[i][0].transcript;
                if (event.results[i].isFinal) final += transcript;
                else interim += transcript;
            }
            userMessageInput.value = (final || interim).trim();
            if (final.trim()) {
                recognition.stop();
            }
        };

        recognition.onerror = (event) => {
            if (event.error === "not-allowed" || event.error === "service-not-allowed") {
                appendNotice("Microphone access was blocked. Please allow mic permission to use voice input.");
            } else if (event.error !== "no-speech" && event.error !== "aborted") {
                appendNotice("Voice input error: " + event.error);
            }
        };

        recognition.onend = () => {
            isListening = false;
            micBtn.classList.remove("listening");
            activeRecognition = null;
            const finalText = userMessageInput.value.trim();
            if (finalText) sendMessage();
        };

        activeRecognition = recognition;
        try {
            recognition.start();
        } catch (err) {
            isListening = false;
            micBtn.classList.remove("listening");
            appendNotice("Could not start voice input. Please try again.");
        }
    }
})();
