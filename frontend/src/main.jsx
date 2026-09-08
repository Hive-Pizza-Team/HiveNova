import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { BrowserRouter } from 'react-router'
import App from './App.jsx'
import './styles.css'

document.cookie = 'hn_ui=react;path=/;max-age=31536000;samesite=lax'

createRoot(document.getElementById('root')).render(
  <StrictMode>
    <BrowserRouter basename="/react">
      <App />
    </BrowserRouter>
  </StrictMode>,
)
