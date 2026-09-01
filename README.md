# Versandberechnung für Leitern und Gerüst

[![CI](https://github.com/SigauriM/OXID_Shopsystem/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/SigauriM/OXID_Shopsystem/actions/workflows/ci.yml?query=branch%3Amain)

OXID-7-Modul: Versand nach **Länge, Gurtmaß, Gewicht und PLZ**, nicht nach einer Pauschale. Ein Auftrag kann in Paket und Spedition zerfallen.

## Regeln

Schwellen sind eine **Testfixtur**; produktive Werte kommen über die Tarifverwaltung.

| | Paket → Sperrgut | → Spedition |
|---|---|---|
| Länge | > 2000 mm | > 4000 mm |
| Gurtmaß | > 3000 mm | > 3600 mm |
| Gewicht | | > 20 000 g |
| Bestellgewicht N | | > 40 000 g hebt auf Spedition |

Volumengewicht: `dimFactorCmKg = 5000`. Goldfall **LAD-200 + LAD-500**, PLZ `01067`: **66,00 €** (Paket 6,00 € + Spedition 60,00 €).

## Start (Git Bash / WSL)

```bash
cp .env.example .env   # MYSQL_* und ADMIN_PASSWORD setzen
bash bin/dev-setup.sh
```

Ein Lauf: Docker, Shop, Modul, Tarif, Katalog (Kategorie „Gerüst und Leitern“, zehn Artikel). Shop [http://127.0.0.1:8080](http://127.0.0.1:8080), Admin [http://127.0.0.1:8080/admin/](http://127.0.0.1:8080/admin/).

## Tests

CI auf `main` (Push und Pull Request): PHPUnit, PHPStan 6, PHP_CodeSniffer PSR-12. Kern PHP 8.2 und 8.3, Modul PHP 8.3. **Schreiben des Tarifs in die Datenbank wird nur lokal** im Docker-Shop geprüft, nicht in GitHub Actions.

JSON-LD `Product` auf der Artikelkarte (Menge 1). Geprüft mit [validator.schema.org](https://validator.schema.org/) im Modus **Code** und mit dem Google Rich Results Test im **Code-Modus** (kein öffentlicher Shop-URL). Null Fehler; erwartete Warnungen: fehlendes `handlingTime` und `hasMerchantReturnPolicy`. Staff-RDFa (GoodRelations) bleibt aus — es würde `oxaddsum = 0` als Versandpreis veröffentlichen.

## Was das Modul nicht tut

Kein Bin-Packing, kein CH/Zoll, keine DHL/DPD-API, kein vollständiges PLZ-Verzeichnis (unbekannte PLZ: fail closed, kein Festland-Fallback). Die Auszeichnung beschreibt nur einen Artikel in Menge 1; `handlingTime` wird nicht behauptet.

## Bilder

![Klassifizierung in der Admin](docs/img/admin-tariff.png)

![Rechner in der Admin](docs/img/admin-sandbox.png)

![Warenkorb: Paket + Spedition](docs/img/basket-split.png)

![Versand auf der Artikelkarte](docs/img/product-versand.png)

![validator.schema.org, Code-Modus](docs/img/schema-validator.png)

![Rechner, kurzer Lauf](docs/img/sandbox.gif)
