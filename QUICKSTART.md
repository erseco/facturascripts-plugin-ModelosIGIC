# Quick Start Guide

## 1. Start FacturaScripts

```bash
make up
```

Wait for the containers to start (about 30 seconds).

## 2. Access FacturaScripts

Open your browser and go to:
```
http://localhost:8081
```

Login with:
- **Username:** `admin`
- **Password:** `admin`

## 3. Enable the Plugin

Go to **Admin Panel → Plugins** and enable **ModelosIGIC**.

Or run:
```bash
make enable-plugin
```

## 4. Try it

Create a few sales and purchase invoices with IGIC taxes, then open
**Reports → Modelo 420**, pick the quarter and press **Calcular**.

## 5. Make Changes

Edit any file in the plugin directory. Changes are reflected immediately.

After changing models or controllers, rebuild:
```bash
make rebuild
```

## 6. Stop FacturaScripts

```bash
make down
```

## Need Help?

Run `make help` to see all available commands.

Check the full [README.md](README.md) for detailed documentation.
