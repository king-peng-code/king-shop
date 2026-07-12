import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import './index.css'
import App from './App.tsx'

const appName = import.meta.env.VITE_APP_NAME || 'King Shop'
document.title = `${appName} 管理后台`

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <App />
  </StrictMode>,
)
