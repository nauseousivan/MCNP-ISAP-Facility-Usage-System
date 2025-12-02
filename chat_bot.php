<?php
// chat_bot.php - Fixed version with improved messaging and UI

$faq_responses = [
    // Facility Booking
    'book' => '📅 **Facility Booking**\nGo to Dashboard → Facility Requests → Fill out the form with event details\n\n**Pagt-book ng Facility**\nPunta sa Dashboard → Facility Requests → Fill-up-an ang form na may details ng event',
    'paano mag book' => '📅 **Pagt-book ng Facility**\nPunta sa Dashboard → Facility Requests → Fill-up-an ang form\n\n**Facility Booking**\nGo to Dashboard → Facility Requests → Fill out the form',
    'reserve' => '📅 **Reserve Facility**\nGo to Dashboard → Click "New Request" → Select facility → Choose date/time\n\n**Mag-reserve**\nPunta sa Dashboard → Pindutin "New Request" → Pumili ng facility → Piliin ang date/time',
    
    // Registration
    'register' => '📝 **Registration**\nClick Register tab → Fill personal details → Verify email → Wait for admin approval\n\n**Pagre-register**\nPindutin ang Register → Lagyan ng personal details → i-verify ang email → Hintayin ang approval ng admin',
    'paano mag register' => '📝 **Pagre-register**\nPindutin ang Register → Lagyan ng details → i-verify ang email → Hintayin ang approval\n\n**Registration**\nClick Register → Fill details → Verify email → Wait approval',
    
    // Login Issues
    'login' => '🔐 **Login Issues**\nMake sure: 1. Email is verified 2. Account is approved 3. Correct password\n\n**Problema sa Login**\nSiguraduhin: 1. Verified ang email 2. Approved ang account 3. Tamang password',
    'hindi maka login' => '🔐 **Hindi Maka-login**\nSiguraduhin: 1. Na-verify na ang email 2. Na-approve na ang account 3. Tamang password\n\n**Cannot Login**\nMake sure: 1. Email verified 2. Account approved 3. Correct password',
    
    // Facilities
    'facilities' => '🏢 **Available Facilities**\nClassrooms, Computer Labs, Science Labs, Conference Rooms, Gymnasium\n\n**Mga Available na Facility**\nClassrooms, Computer Labs, Science Labs, Conference Rooms, Gymnasium',
    'anong facilities' => '🏢 **Mga Available na Facility**\nClassrooms, Laboratory, Conference Rooms, Gym, Study Rooms\n\n**Available Facilities**\nClassrooms, Labs, Conference Rooms, Gym, Study Rooms',
    
    // Contact & Support
    'contact' => '📞 **Contact Admin**\nEmail: admin@mcnp.edu.ph | Visit: General Services Office\n\n**Makipag-ugnayan**\nEmail: admin@mcnp.edu.ph | Puntahan: General Services Office',
    
    // Default
    'default' => '❓ **Need Help?**\nContact admin@mcnp.edu.ph for assistance\n\n**Kailangan ng Tulong?**\nMakipag-ugnayan sa admin@mcnp.edu.ph para sa tulong'
];

$suggested_questions = [
    'How to book facility?',
    'Paano mag-register ng account?', 
    'Anong facilities ang available?',
    'Hindi ako maka-login',
    'Paano i-check ang status ng booking?',
    'Sino ang dapat i-contact?'
];

// FIX: Check if it's an AJAX request first
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['question'])) {
    $question = strtolower($_POST['question']);
    $answer = $faq_responses['default'];
    
    foreach ($faq_responses as $key => $response) {
        if ($key !== 'default' && strpos($question, $key) !== false) {
            $answer = $response;
            break;
        }
    }
    
    echo json_encode(['answer' => $answer]);
    exit;
}

// FIX: Check if it's a GET request for suggestions
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['get_suggestions'])) {
    echo json_encode($suggested_questions);
    exit;
}
?>

<div id="chatBotContainer">
    <div id="chatButton">
        <img src="combined-logo.png" alt="Chat Bot">
        <div class="online-indicator"></div>
    </div>
        
    <div id="chatWindow">
            <div id="chatHeader">
                <img src="ariana2.png" alt="Ariana Grande">
                <div class="header-text">
                    <div class="header-title">Ariana Grande</div>
                    <div class="header-subtitle">Facility Assistant • Online</div>
                </div>
                <div class="header-actions">
                    <button onclick="closeChat()" class="close-btn" title="Close Chat">×</button>
                </div>
            </div>
            
            <div id="chatMessages">
                <div class="welcome-message-container">
                    <div class="welcome-message">
                        🤖 Hi! I'm Ariana - your MCNP-ISAP facility assistant. Ask me anything about bookings, registration, or facilities! 
                        <div class="welcome-subtitle">Nagsasalita ako ng English at Tagalog! 🇵🇭</div>
                    </div>
                </div>
                
                <div id="suggestedQuestions"></div>
            </div>
            
            <div class="chat-input-area">
                <div class="input-wrapper">
                    <input type="text" id="chatInput" placeholder="Type your question here...">
                    <button onclick="startSpeechRecognition()" class="mic-btn" title="Use Microphone">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M3.5 6.5A.5.5 0 0 1 4 7v1a4 4 0 0 0 8 0V7a.5.5 0 0 1 1 0v1a5 5 0 0 1-4.5 4.975V15h3a.5.5 0 0 1 0 1h-7a.5.5 0 0 1 0-1h3v-2.025A5 5 0 0 1 3 8V7a.5.5 0 0 1 .5-.5z"/><path d="M10 8a2 2 0 1 1-4 0V3a2 2 0 1 1 4 0v5zM8 0a3 3 0 0 0-3 3v5a3 3 0 0 0 6 0V3a3 3 0 0 0-3-3z"/></svg>
                    </button>
                    <button onclick="sendMessage()" class="send-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M15.854.146a.5.5 0 0 1 .11.54l-5.819 14.547a.75.75 0 0 1-1.329.124l-3.178-4.995L.643 7.184a.75.75 0 0 1 .124-1.33L15.314.037a.5.5 0 0 1 .54.11ZM6.636 10.07l2.761 4.338L14.13 2.576 6.636 10.07Zm6.787-8.201L1.591 6.602l4.339 2.76 7.494-7.493Z"/></svg>
                    </button>
                </div>
                <div class="input-hint">
                    💡 Click questions above or type your own
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Chatbot Main Container */
#chatBotContainer {
    position: fixed;
    bottom: 40px;
    right: 40px;
    z-index: 9999;
}

/* Chat Bubble Button */
#chatButton {
    background: var(--accent-color);
    color: white;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    transition: all 0.3s ease;
    position: relative;
}
#chatButton:hover {
    transform: scale(1.1);
    box-shadow: 0 12px 35px rgba(0,0,0,0.2);
}
#chatButton img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 50%;
}
.online-indicator {
    position: absolute;
    top: -2px;
    right: -2px;
    width: 14px;
    height: 14px;
    background: #22c55e;
    border-radius: 50%;
    border: 3px solid var(--bg-primary);
}

/* Chat Window */
#chatWindow {
    display: none;
    width: 380px;
    height: 550px;
    background: var(--bg-secondary);
    border-radius: 20px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.2);
    position: absolute;
    bottom: 80px;
    right: 0;
    overflow: hidden;
    border: 1px solid var(--border-color);
    display: none;
    flex-direction: column;
}
#chatWindow.open {
    display: flex;
}

/* Chat Header */
#chatHeader {
    background: var(--accent-color);
    color: var(--bg-primary);
    padding: 20px;
    display: flex;
    align-items: center;
    position: relative;
    z-index: 10;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}
#chatHeader img {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    object-fit: cover;
    margin-right: 12px;
    border: 2px solid rgba(255,255,255,0.3);
}
.header-text { flex: 1; }
.header-title { font-weight: 600; font-size: 16px; margin-bottom: 2px; }
.header-subtitle { font-size: 12px; opacity: 0.9; }
.header-actions { display: flex; gap: 8px; }
.clear-btn, .close-btn {
    width: 32px; height: 32px; border-radius: 50%;
    background: rgba(255,255,255,0.2); color: white;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; font-size: 14px; border: none;
}
.clear-btn svg {
    width: 16px;
    height: 16px;
}
.clear-btn:hover, .close-btn:hover { background: rgba(255,255,255,0.3); }

/* Chat Messages Area */
#chatMessages {
    flex-grow: 1;
    overflow-y: auto;
    padding: 20px;
    background: var(--bg-secondary);
}
.welcome-message-container { text-align: center; margin-bottom: 20px; }
.welcome-message {
    background: var(--bg-primary);
    padding: 16px 20px;
    border-radius: 20px;
    display: inline-block;
    box-shadow: 0 4px 15px rgba(0,0,0,0.05);
    font-size: 14px;
    color: var(--text-secondary);
    line-height: 1.5;
    max-width: 90%;
}
.welcome-subtitle { margin-top: 8px; font-size: 12px; color: var(--text-secondary); opacity: 0.8; }

/* Chat Input Area */
.chat-input-area {
    padding: 20px;
    background: var(--bg-primary);
    border-top: 1px solid var(--border-color);
}
.input-wrapper { position: relative; }
#chatInput {
    width: 100%;
    padding: 14px 80px 14px 20px;
    border: 2px solid var(--border-color);
    border-radius: 999px;
    outline: none;
    font-size: 14px;
    transition: all 0.3s;
    background: var(--bg-secondary);
    color: var(--text-primary);
}
#chatInput:focus {
    border-color: var(--accent-color);
    background: var(--bg-primary);
    box-shadow: 0 0 0 3px var(--accent-color-translucent, rgba(102, 126, 234, 0.2));
}
.send-btn {
    position: absolute; right: 4px; top: 50%;
    transform: translateY(-50%);
    background: var(--accent-color);
    color: var(--bg-primary);
    border: none; width: 40px; height: 40px;
    border-radius: 50%; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    font-size: 16px; transition: all 0.3s;
}
.send-btn:hover { opacity: 0.8; }
.mic-btn {
    position: absolute; right: 50px; top: 50%;
    transform: translateY(-50%);
    background: transparent;
    color: var(--text-secondary);
    border: none; width: 32px; height: 32px;
    border-radius: 50%; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    font-size: 16px; transition: all 0.3s;
}
.mic-btn:hover {
    background: var(--border-color);
    color: var(--text-primary);
}
.mic-btn.recording {
    color: var(--accent-color);
    animation: pulse 1.5s infinite;
}
.input-hint { text-align: center; margin-top: 12px; font-size: 11px; color: var(--text-secondary); }

/* User and Bot Messages */
.chat-message-user {
    text-align: right;
    margin: 12px 0;
    animation: slideInRight 0.3s ease;
}

.chat-message-user span {
    background: var(--accent-color);
    color: var(--bg-primary);
    padding: 12px 18px;
    border-radius: 20px 20px 6px 20px;
    display: inline-block;
    max-width: 85%;
    word-wrap: break-word;
    line-height: 1.4;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.chat-message-bot {
    text-align: left;
    margin: 12px 0;
    animation: slideInLeft 0.3s ease;
}

.chat-message-bot span {
    background: var(--bg-primary);
    color: var(--text-primary);
    padding: 14px 18px;
    border-radius: 20px 20px 20px 6px;
    display: inline-block;
    max-width: 85%;
    word-wrap: break-word;
    line-height: 1.5;
    box-shadow: 0 4px 15px rgba(0,0,0,0.05);
    border: 1px solid var(--border-color);
}

/* TYPING INDICATOR STYLES */
.typing-indicator span {
    /* Use a dedicated style for the typing bubble */
    background: #e2e8f0 !important;
    color: #334155 !important;
    padding: 14px 18px;
    border-radius: 20px 20px 20px 6px;
    display: flex !important;
    align-items: center;
    width: fit-content;
    max-width: 85%;
    box-shadow: none !important;
    border: none !important;
}

.dot {
    height: 8px;
    width: 8px;
    background-color: #94a3b8;
    border-radius: 50%;
    margin: 0 2px;
    display: inline-block;
    animation: typing-bounce 1.5s infinite ease-in-out;
}

.dot:nth-child(2) {
    animation-delay: 0.2s;
}

.dot:nth-child(3) {
    animation-delay: 0.4s;
}

@keyframes typing-bounce {
    0%, 80%, 100% { transform: translateY(0); }
    40% { transform: translateY(-6px); }
}
/* END TYPING INDICATOR STYLES */


/* Improved message formatting */
.bot-message-content {
    line-height: 1.6;
}

.english-section {
    margin-bottom: 12px;
}

.tagalog-section {
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid var(--border-color);
    color: var(--text-primary);
    font-weight: 500;
}

.section-title {
    font-weight: 600;
    color: var(--accent-color);
    margin-bottom: 6px;
    font-size: 14px;
}

.suggested-question {
    background: white;
    border: 2px solid #e2e8f0;
    padding: 12px 16px;
    border-radius: 16px;
    font-size: 13px;
    cursor: pointer;
    transition: all 0.3s ease;
    text-align: left;
    color: var(--text-secondary);
    font-weight: 500;
    box-shadow: 0 2px 8px rgba(0,0,0,0.03);
}

.suggested-question:hover {
    background: var(--accent-color);
    color: var(--bg-primary);
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    border-color: transparent;
}

@keyframes slideInRight {
    from { transform: translateX(20px); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}

@keyframes slideInLeft {
    from { transform: translateX(-20px); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}

.typing-cursor {
    display: inline-block;
    width: 8px;
    height: 1.2em;
    background-color: var(--text-primary);
    animation: blink 1s step-end infinite;
}
@keyframes blink {
    50% { opacity: 0; }
}

#chatMessages::-webkit-scrollbar {
    width: 5px;
}

#chatMessages::-webkit-scrollbar-track {
    background: transparent;
    border-radius: 3px;
}

#chatMessages::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 3px;
}

/* Mobile Responsiveness */
@media (max-width: 768px) {
    #chatBotContainer {
        bottom: 20px;
        right: 20px;
    }

    #chatButton {
        width: 50px;
        height: 50px;
    }

    #chatWindow {
        width: calc(100vw - 40px); /* Full width with some padding */
        height: calc(100vh - 100px); /* Almost full height */
        bottom: 70px; /* Position above the chat button */
        right: 0;
        max-width: 400px;
        max-height: 600px;
    }

    #chatHeader {
        padding: 16px;
    }

    .chat-input-area, #chatMessages {
        padding: 16px;
    }
}
</style>

<script>
let chatOpen = false;

// Open chat on button click
document.getElementById('chatButton').addEventListener('click', function(e) {
    e.stopPropagation(); // Prevent event bubbling
    openChat();
});

// Close chat when clicking outside
document.addEventListener('click', function(e) {
    if (chatOpen && !document.getElementById('chatBotContainer').contains(e.target)) {
        closeChat();
    }
});

function openChat() {
    const chatWindow = document.getElementById('chatWindow');
    const chatButton = document.getElementById('chatButton');
    
    chatOpen = true;
    chatWindow.style.display = 'flex';
    loadSuggestedQuestions();
    
    // Auto-focus input when opening
    setTimeout(() => {
        document.getElementById('chatInput').focus();
    }, 300);
}

function closeChat() {
    const chatWindow = document.getElementById('chatWindow');
    const chatButton = document.getElementById('chatButton');
    
    chatOpen = false;
    chatWindow.style.display = 'none';
}

function clearChat() {
    const messages = document.getElementById('chatMessages');
    messages.innerHTML = `
        <div class="welcome-message-container">
            <div class="welcome-message">
                🤖 Hi! I'm Ariana - your MCNP-ISAP facility assistant. Ask me anything about bookings, registration, or facilities! 
                <div class="welcome-subtitle">Nagsasalita ako ng English at Tagalog! 🇵🇭</div>
            </div>
        </div>
        <div id="suggestedQuestions"></div>
    `;
    loadSuggestedQuestions();
    document.getElementById('chatInput').focus();
}

// Keep the rest of your functions the same...
function loadSuggestedQuestions() {
    fetch('chat_bot.php?get_suggestions=true')
        .then(response => response.json())
        .then(questions => {
            const container = document.getElementById('suggestedQuestions');
            container.innerHTML = questions.map(q => 
                `<div class="suggested-question" onclick="askQuestion('${q.replace(/'/g, "\\'")}')">${q}</div>`
            ).join('');
        });
}

function typeBotMessage(element, text) {
    // This improved function will type out the message while correctly rendering HTML.
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = text;
    const allTextNodes = [];
    
    function getTextNodes(node) {
        if (node.nodeType === 3) {
            allTextNodes.push(node);
        } else {
            for (const child of node.childNodes) {
                getTextNodes(child);
            }
        }
    }

    getTextNodes(tempDiv);
    element.innerHTML = text;

    // This part is a visual trick and can be removed if not desired.
    // It makes the text appear character by character.
    element.style.visibility = 'hidden';
    setTimeout(() => {
        element.style.visibility = 'visible';
    }, 150);
}


function playNotificationSound() {
    // IMPORTANT: You need to add a sound file at this path.
    // For example, create a 'sounds' folder and place 'notification.mp3' inside it.
    // You can find free notification sounds online.
    const audio = new Audio('sounds/notification.mp3');
    audio.play().catch(error => {
        // Autoplay can be blocked by the browser if the user hasn't interacted with the page yet.
        console.log("Chat notification sound was blocked by the browser.");
    });
}

function askQuestion(question) {
    document.getElementById('chatInput').value = question;
    sendMessage();
}

function sendMessage() {
    const input = document.getElementById('chatInput');
    const question = input.value.trim();
    
    if (!question) return;
    
    input.value = '';
    
    const messages = document.getElementById('chatMessages');
    messages.innerHTML += `
        <div class="chat-message-user">
            <span>${question}</span>
        </div>`;
    
    document.getElementById('suggestedQuestions').style.display = 'none';
    
    // TYPING BUBBLE ANIMATION
    messages.innerHTML += `
        <div class="chat-message-bot typing-indicator" id="typingIndicator">
            <span>
                <div class="dot"></div>
                <div class="dot"></div>
                <div class="dot"></div>
            </span>
        </div>`;
    
    messages.scrollTop = messages.scrollHeight;
    
    setTimeout(() => {
        fetch('chat_bot.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'question=' + encodeURIComponent(question)
        })
        .then(response => response.json())
        .then(data => {
            document.getElementById('typingIndicator').remove();
            playNotificationSound();

            const formattedAnswer = formatBotResponse(data.answer);
            const botMessageContainer = document.createElement('div');
            botMessageContainer.className = 'chat-message-bot';
            botMessageContainer.innerHTML = `
                <div class="chat-message-bot">
                    <span>
                        <div class="bot-message-content"></div>
                    </span>
                </div>`;
            messages.appendChild(botMessageContainer);
            const contentElement = botMessageContainer.querySelector('.bot-message-content');
            typeBotMessage(contentElement, formattedAnswer);

            // Re-show suggested questions after bot responds
            document.getElementById('suggestedQuestions').style.display = 'grid';

            messages.scrollTop = messages.scrollHeight;
        });
    }, 1000 + Math.random() * 1000);
}

function formatBotResponse(answer) {
    const sections = answer.split('\\n\\n');
    let formattedHTML = '';
    
    sections.forEach(section => {
        if (section.includes('**') && section.includes('**\\n')) {
            const lines = section.split('\\n');
            const title = lines[0].replace(/\*\*/g, '').trim();
            const content = lines.slice(1).join('<br>');
            
            // Simple check for Tagalog words in the title
            const isTagalog = title.includes('ng') || title.includes('sa') || title.includes('ang') || 
                               title.includes('Pagt-book') || title.includes('Pagre-register') || 
                               title.includes('Problema') || title.includes('Makipag-ugnayan');
            
            const sectionClass = isTagalog ? 'tagalog-section' : 'english-section';
            
            formattedHTML += `
                <div class="${sectionClass}">
                    <div class="section-title">${title}</div>
                    <div>${content}</div>
                </div>
            `;
        } else {
            formattedHTML += `<div>${section.replace(/\\n/g, '<br>')}</div>`;
        }
    });
    
    return formattedHTML;
}

document.getElementById('chatInput').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        sendMessage();
    }
});

function startSpeechRecognition() {
    const micBtn = document.querySelector('.mic-btn');
    const chatInput = document.getElementById('chatInput');

    if (!('webkitSpeechRecognition' in window)) {
        alert('Speech recognition is not supported in your browser. Please use Google Chrome.');
        return;
    }

    const recognition = new webkitSpeechRecognition();
    recognition.continuous = false;
    recognition.interimResults = true;
    recognition.lang = 'en-US';

    recognition.onstart = function() {
        micBtn.classList.add('recording');
        chatInput.placeholder = 'Listening...';
    };

    recognition.onresult = function(event) {
        chatInput.value = event.results[0][0].transcript;
    };

    recognition.onend = function() {
        micBtn.classList.remove('recording');
        chatInput.placeholder = 'Type your question here...';
    };

    recognition.start();
}

loadSuggestedQuestions();
</script>