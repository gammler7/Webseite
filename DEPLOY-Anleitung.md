# Automatische Veröffentlichung mit GitHub Pages

Damit jede Änderung an diesem Repository automatisch auf deiner Webseite erscheint, musst du **GitHub Pages** einmal aktivieren. Danach reicht ein normaler `git push`.

---

## Einmalige Einrichtung (ca. 1 Minute)

1. **Repository auf GitHub öffnen**  
   → https://github.com/gammler7/Webseite

2. **Einstellungen öffnen**  
   → Oben im Tab-Menü auf **Settings** klicken.

3. **Seiten-Einstellungen finden**  
   → In der linken Sidebar unter „Code and automation“ auf **Pages** klicken.

4. **Quelle wählen**  
   - Unter **Build and deployment** bei **Source** die Option **„Deploy from a branch“** auswählen.
   - Bei **Branch**: **main** (oder deine Standard-Branch) wählen.
   - Bei **Folder**: **/ (root)** wählen.
   - Auf **Save** klicken.

5. **Kurz warten**  
   Nach ein paar Minuten ist die Seite erreichbar unter:
   - **https://gammler7.github.io/Webseite/**

---

## Ab dann: So aktualisiert sich die Webseite

- Du änderst etwas lokal (z. B. in Cursor).
- Du machst Commit und Push:
  ```bash
  git add .
  git commit -m "Beschreibung der Änderung"
  git push
  ```
- GitHub baut die Seite neu; nach etwa 1–2 Minuten ist die Änderung unter der obigen URL sichtbar.

Fertig – keine weitere manuelle Übertragung nötig.
