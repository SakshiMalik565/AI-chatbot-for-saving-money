document.addEventListener('DOMContentLoaded', function() {
    const chatButton = document.getElementById('chat-button');
    const chatContainer = document.getElementById('chat-container');
    const chatMessages = document.getElementById('chat-messages');
    const chatInput = document.getElementById('chat-input');
    const sendButton = document.getElementById('send-button');

    // Toggle chat container visibility
    chatButton.addEventListener('click', function() {
        chatContainer.classList.toggle('d-none');
    });

    // Send message function
    async function sendMessage() {
        const message = chatInput.value.trim();
        if (message === '') return;

        // Add user message to chat
        addMessage('user', message);
        chatInput.value = '';

        try {
            const response = await fetch('chatbot.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ message: message })
            });

            const data = await response.json();
            
            if (data.error) {
                addMessage('bot', 'Sorry, there was an error processing your request.');
            } else {
                addMessage('bot', data.response);
            }
        } catch (error) {
            addMessage('bot', 'Sorry, there was an error connecting to the chatbot.');
        }
    }

    // Add message to chat
    function addMessage(sender, text) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `chat-message ${sender}-message`;
        
        const messageContent = document.createElement('div');
        messageContent.className = 'message-content';
        messageContent.textContent = text;
        
        messageDiv.appendChild(messageContent);
        chatMessages.appendChild(messageDiv);
        
        // Scroll to bottom
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    // Event listeners
    sendButton.addEventListener('click', sendMessage);
    chatInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            sendMessage();
        }
    });
}); 