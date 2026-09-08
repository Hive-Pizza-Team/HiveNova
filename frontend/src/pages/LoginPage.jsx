import { useState } from 'react'

export default function LoginPage() {
  const [username, setUsername] = useState('')
  const [password, setPassword] = useState('')

  return (
    <div className="hn-login">
      <div className="hn-login-panel">
        <p className="hn-kicker">HiveNova</p>
        <h1>Command deck</h1>
        <p className="hn-muted">Same accounts as the classic game. Hive Keychain still signs on the PHP lobby if you need it.</p>
        <form className="hn-form" method="post" action="/index.php?page=login">
          <label>
            Commander
            <input name="username" autoComplete="username" value={username} onChange={(e) => setUsername(e.target.value)} required />
          </label>
          <label>
            Password
            <input name="password" type="password" autoComplete="current-password" value={password} onChange={(e) => setPassword(e.target.value)} required />
          </label>
          <button type="submit">Enter</button>
        </form>
        <p>
          <a href="/index.php">Classic lobby</a>
        </p>
      </div>
    </div>
  )
}
