import { Navigate, Route, Routes } from 'react-router'
import LoginPage from './pages/LoginPage.jsx'
import GameShell from './shell/GameShell.jsx'
import OverviewPage from './pages/OverviewPage.jsx'
import StubPage from './pages/StubPage.jsx'

export default function App() {
  return (
    <Routes>
      <Route path="/" element={<LoginPage />} />
      <Route path="/login" element={<LoginPage />} />
      <Route element={<GameShell />}>
        <Route path="overview" element={<OverviewPage />} />
        <Route path=":page" element={<StubPage />} />
      </Route>
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  )
}
