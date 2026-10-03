import { Navigate, Route, Routes } from 'react-router'
import LoginPage from './pages/LoginPage.jsx'
import GameShell from './shell/GameShell.jsx'
import OverviewPage from './pages/OverviewPage.jsx'
import EmpirePage from './pages/EmpirePage.jsx'
import QueueYardPage from './pages/QueueYardPage.jsx'
import FleetPage from './pages/FleetPage.jsx'
import GalaxyPage from './pages/GalaxyPage.jsx'
import MessagesPage from './pages/MessagesPage.jsx'
import MarketPage from './pages/MarketPage.jsx'
import StubPage from './pages/StubPage.jsx'

export default function App() {
  return (
    <Routes>
      <Route path="/" element={<LoginPage />} />
      <Route path="/login" element={<LoginPage />} />
      <Route element={<GameShell />}>
        <Route path="overview" element={<OverviewPage />} />
        <Route path="empire" element={<EmpirePage />} />
        <Route path="buildings" element={<QueueYardPage resource="buildings" idField="building" title="Buildings" />} />
        <Route path="research" element={<QueueYardPage resource="research" idField="tech" title="Research" />} />
        <Route path="shipyard" element={<QueueYardPage resource="shipyard" idField="fmenge" title="Shipyard" mode="fleet" />} />
        <Route path="defense" element={<QueueYardPage resource="shipyard" idField="fmenge" title="Defenses" mode="defense" />} />
        <Route path="fleetTable" element={<FleetPage />} />
        <Route path="galaxy" element={<GalaxyPage />} />
        <Route path="messages" element={<MessagesPage />} />
        <Route path="trader" element={<MarketPage />} />
        <Route path="market" element={<Navigate to="/trader" replace />} />
        <Route path=":page" element={<StubPage />} />
      </Route>
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  )
}
