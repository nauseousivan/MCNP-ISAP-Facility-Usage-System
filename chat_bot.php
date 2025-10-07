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

<!-- Add this wrapper at the top of your HTML section -->
<div id="chatBotContainer" style="position: fixed; bottom: 40px; right: 40px; z-index: 9999;">
    <div id="chatButton" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; width: 60px; height: 60px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 24px; box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4); transition: all 0.3s ease; position: relative;">
        <img src="combined-logo.png" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
        <div style="position: absolute; top: -5px; right: -5px; width: 12px; height: 12px; background: #22c55e; border-radius: 50%; border: 2px solid white;"></div>
    </div>
        
    <div id="chatWindow" style="display: none; width: 380px; height: 550px; background: white; border-radius: 20px; box-shadow: 0 20px 60px rgba(0,0,0,0.2); position: absolute; bottom: 80px; right: 0; overflow: hidden; border: 1px solid rgba(255,255,255,0.2);">
            <div id="chatHeader" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; display: flex; align-items: center; position: relative; z-index: 10; box-shadow: 0 4px 15px rgba(0,0,0,0.15);">
                <img src="ariana2.png" style="width: 45px; height: 45px; border-radius: 50%; object-fit: cover; margin-right: 12px; border: 2px solid rgba(255,255,255,0.3);">
                <div style="flex: 1;">
                    <div style="font-weight: 600; font-size: 16px; margin-bottom: 2px;">Ariana Grande</div>
                    <div style="font-size: 12px; opacity: 0.9;">MCNP Facility Assistant • Online</div>
                </div>
                <div style="display: flex; gap: 8px;">
                    <div onclick="toggleChat()" style="width: 32px; height: 32px; border-radius: 50%; background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 14px;">×</div>
                </div>
            </div>
            
            <div id="chatMessages" style="height: 340px; overflow-y: auto; padding: 20px; background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);">
                <div style="text-align: center; margin-bottom: 20px;">
                    <div style="background: white; padding: 16px 20px; border-radius: 20px; display: inline-block; box-shadow: 0 4px 15px rgba(0,0,0,0.08); font-size: 14px; color: #64748b; line-height: 1.5; max-width: 80%;">
                        🤖 Hi! I'm Ariana - your MCNP facility assistant. Ask me anything about bookings, registration, or facilities! 
                        <div style="margin-top: 8px; font-size: 12px; color: #94a3b8;">Nagsasalita ako ng English at Tagalog! 🇵🇭</div>
                    </div>
                </div>
                
                <div id="suggestedQuestions" style="display: grid; gap: 10px; margin-bottom: 15px;">
                    </div>
            </div>
            
            <div style="padding: 20px; background: white; border-top: 1px solid #f1f5f9;">
                <div style="position: relative;">
                    <input type="text" id="chatInput" placeholder="Type your question here..." 
                            style="width: 100%; padding: 14px 50px 14px 20px; border: 2px solid #e2e8f0; border-radius: 25px; outline: none; font-size: 14px; transition: all 0.3s; background: #f8fafc;">
                    <button onclick="sendMessage()" style="position: absolute; right: 6px; top: 50%; transform: translateY(-50%); background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; width: 36px; height: 36px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 16px; transition: all 0.3s;">
                        →
                    </button>
                </div>
                <div style="text-align: center; margin-top: 12px; font-size: 11px; color: #94a3b8;">
                    💡 Click questions above or type your own
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Modern Chat Styles */
#chatButton:hover {
    transform: scale(1.1);
    box-shadow: 0 12px 35px rgba(102, 126, 234, 0.6);
}

#chatInput:focus {
    border-color: #667eea;
    background: white;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.chat-message-user {
    text-align: right;
    margin: 12px 0;
    animation: slideInRight 0.3s ease;
}

.chat-message-user span {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 12px 18px;
    border-radius: 20px 20px 6px 20px;
    display: inline-block;
    max-width: 85%;
    word-wrap: break-word;
    line-height: 1.4;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}

.chat-message-bot {
    text-align: left;
    margin: 12px 0;
    animation: slideInLeft 0.3s ease;
}

.chat-message-bot span {
    background: white;
    color: #334155;
    padding: 14px 18px;
    border-radius: 20px 20px 20px 6px;
    display: inline-block;
    max-width: 85%;
    word-wrap: break-word;
    line-height: 1.5;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    border: 1px solid #f1f5f9;
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
    border-top: 1px solid #e2e8f0;
    color: #1e293b;
    font-weight: 500;
}

.section-title {
    font-weight: 600;
    color: #667eea;
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
    color: #475569;
    font-weight: 500;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.suggested-question:hover {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
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

#chatMessages::-webkit-scrollbar {
    width: 6px;
}

#chatMessages::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 3px;
}

#chatMessages::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 3px;
}
</style>

<script>
let chatOpen = false;
let autoOpenTimer;

// Toggle chat on button click
document.getElementById('chatButton').addEventListener('click', function(e) {
    e.stopPropagation(); // Prevent event bubbling
    toggleChat();
});

// Close chat when clicking outside
document.addEventListener('click', function(e) {
    if (chatOpen && !document.getElementById('chatBotContainer').contains(e.target)) {
        toggleChat();
    }
});

// Optional: Keep your hover functionality but make it faster
document.getElementById('chatBotContainer').addEventListener('mouseenter', function() {
    if (!chatOpen) {
        autoOpenTimer = setTimeout(() => {
            toggleChat();
        }, 500); // Reduced from 1000ms to 500ms for better UX
    }
});

document.getElementById('chatBotContainer').addEventListener('mouseleave', function() {
    clearTimeout(autoOpenTimer);
});

function toggleChat() {
    const chatWindow = document.getElementById('chatWindow');
    const chatButton = document.getElementById('chatButton');
    
    chatOpen = !chatOpen;
    chatWindow.style.display = chatOpen ? 'block' : 'none';
    
    if (chatOpen) {
        chatButton.style.transform = 'scale(1.1)';
        chatButton.style.boxShadow = '0 12px 35px rgba(102, 126, 234, 0.6)';
        loadSuggestedQuestions();
        
        // Auto-focus input when opening
        setTimeout(() => {
            document.getElementById('chatInput').focus();
        }, 300);
    } else {
        chatButton.style.transform = 'scale(1)';
        chatButton.style.boxShadow = '0 8px 25px rgba(102, 126, 234, 0.4)';
    }
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
            
            const formattedAnswer = formatBotResponse(data.answer);
            
            messages.innerHTML += `
                <div class="chat-message-bot">
                    <span>
                        <div class="bot-message-content">
                            ${formattedAnswer}
                        </div>
                    </span>
                </div>`;
            
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

loadSuggestedQuestions();
</script>