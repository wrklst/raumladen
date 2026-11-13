<?php

namespace App\Datev;

use Carbon\CarbonInterface;

/**
 * DATEV-Format "Buchungsstapel", EXTF version 700: 125 columns, Windows-1252,
 * CRLF, semicolon separated, text in double quotes, decimal comma.
 */
class DatevExtfWriter
{
    /** @var list<string> */
    public const array COLUMNS = [
        'Umsatz (ohne Soll/Haben-Kz)', 'Soll/Haben-Kennzeichen', 'WKZ Umsatz', 'Kurs', 'Basis-Umsatz', 'WKZ Basis-Umsatz',
        'Konto', 'Gegenkonto (ohne BU-Schlüssel)', 'BU-Schlüssel', 'Belegdatum', 'Belegfeld 1', 'Belegfeld 2', 'Skonto',
        'Buchungstext', 'Postensperre', 'Diverse Adressnummer', 'Geschäftspartnerbank', 'Sachverhalt', 'Zinssperre', 'Beleglink',
        'Beleginfo - Art 1', 'Beleginfo - Inhalt 1', 'Beleginfo - Art 2', 'Beleginfo - Inhalt 2', 'Beleginfo - Art 3', 'Beleginfo - Inhalt 3',
        'Beleginfo - Art 4', 'Beleginfo - Inhalt 4', 'Beleginfo - Art 5', 'Beleginfo - Inhalt 5', 'Beleginfo - Art 6', 'Beleginfo - Inhalt 6',
        'Beleginfo - Art 7', 'Beleginfo - Inhalt 7', 'Beleginfo - Art 8', 'Beleginfo - Inhalt 8',
        'KOST1 - Kostenstelle', 'KOST2 - Kostenstelle', 'Kost-Menge', 'EU-Land u. UStID (Bestimmung)', 'EU-Steuersatz (Bestimmung)',
        'Abw. Versteuerungsart', 'Sachverhalt L+L', 'Funktionsergänzung L+L', 'BU 49 Hauptfunktionstyp', 'BU 49 Hauptfunktionsnummer',
        'BU 49 Funktionsergänzung',
        'Zusatzinformation - Art 1', 'Zusatzinformation- Inhalt 1', 'Zusatzinformation - Art 2', 'Zusatzinformation- Inhalt 2',
        'Zusatzinformation - Art 3', 'Zusatzinformation- Inhalt 3', 'Zusatzinformation - Art 4', 'Zusatzinformation- Inhalt 4',
        'Zusatzinformation - Art 5', 'Zusatzinformation- Inhalt 5', 'Zusatzinformation - Art 6', 'Zusatzinformation- Inhalt 6',
        'Zusatzinformation - Art 7', 'Zusatzinformation- Inhalt 7', 'Zusatzinformation - Art 8', 'Zusatzinformation- Inhalt 8',
        'Zusatzinformation - Art 9', 'Zusatzinformation- Inhalt 9', 'Zusatzinformation - Art 10', 'Zusatzinformation- Inhalt 10',
        'Zusatzinformation - Art 11', 'Zusatzinformation- Inhalt 11', 'Zusatzinformation - Art 12', 'Zusatzinformation- Inhalt 12',
        'Zusatzinformation - Art 13', 'Zusatzinformation- Inhalt 13', 'Zusatzinformation - Art 14', 'Zusatzinformation- Inhalt 14',
        'Zusatzinformation - Art 15', 'Zusatzinformation- Inhalt 15', 'Zusatzinformation - Art 16', 'Zusatzinformation- Inhalt 16',
        'Zusatzinformation - Art 17', 'Zusatzinformation- Inhalt 17', 'Zusatzinformation - Art 18', 'Zusatzinformation- Inhalt 18',
        'Zusatzinformation - Art 19', 'Zusatzinformation- Inhalt 19', 'Zusatzinformation - Art 20', 'Zusatzinformation- Inhalt 20',
        'Stück', 'Gewicht', 'Zahlweise', 'Forderungsart', 'Veranlagungsjahr', 'Zugeordnete Fälligkeit', 'Skontotyp', 'Auftragsnummer',
        'Buchungstyp', 'USt-Schlüssel (Anzahlungen)', 'EU-Land (Anzahlungen)', 'Sachverhalt L+L (Anzahlungen)', 'EU-Steuersatz (Anzahlungen)',
        'Erlöskonto (Anzahlungen)', 'Herkunft-Kz', 'Buchungs GUID', 'KOST-Datum', 'SEPA-Mandatsreferenz', 'Skontosperre', 'Gesellschaftername',
        'Beteiligtennummer', 'Identifikationsnummer', 'Zeichnernummer', 'Postensperre bis', 'Bezeichnung SoBil-Sachverhalt',
        'Kennzeichen SoBil-Buchung', 'Festschreibung', 'Leistungsdatum', 'Datum Zuord. Steuerperiode', 'Fälligkeit', 'Generalumkehr (GU)',
        'Steuersatz', 'Land', 'Abrechnungsreferenz', 'BVV-Position', 'EU-Land u. UStID (Ursprung)', 'EU-Steuersatz (Ursprung)', 'Abw. Skontokonto',
    ];

    /** @var list<string> */
    private const array TEXT_COLUMNS = [
        'Soll/Haben-Kennzeichen', 'WKZ Umsatz', 'WKZ Basis-Umsatz', 'BU-Schlüssel', 'Belegfeld 1', 'Belegfeld 2', 'Buchungstext',
        'Diverse Adressnummer', 'Beleglink', 'KOST1 - Kostenstelle', 'KOST2 - Kostenstelle', 'EU-Land u. UStID (Bestimmung)',
        'Auftragsnummer', 'Buchungs GUID', 'SEPA-Mandatsreferenz', 'Gesellschaftername', 'Identifikationsnummer', 'Zeichnernummer',
        'Bezeichnung SoBil-Sachverhalt', 'Land', 'Abrechnungsreferenz', 'EU-Land u. UStID (Ursprung)', 'Herkunft-Kz',
        'Beleginfo - Art 1', 'Beleginfo - Inhalt 1', 'Beleginfo - Art 2', 'Beleginfo - Inhalt 2', 'Beleginfo - Art 3', 'Beleginfo - Inhalt 3',
        'Beleginfo - Art 4', 'Beleginfo - Inhalt 4', 'Beleginfo - Art 5', 'Beleginfo - Inhalt 5', 'Beleginfo - Art 6', 'Beleginfo - Inhalt 6',
        'Beleginfo - Art 7', 'Beleginfo - Inhalt 7', 'Beleginfo - Art 8', 'Beleginfo - Inhalt 8',
    ];

    /** @var list<string> */
    private const array AMOUNT_COLUMNS = ['Umsatz (ohne Soll/Haben-Kz)', 'Basis-Umsatz', 'Skonto', 'EU-Steuersatz (Bestimmung)', 'EU-Steuersatz (Ursprung)', 'Steuersatz'];

    /**
     * @param  list<array<string, mixed>>  $bookings  column name => value
     */
    public function write(array $bookings, CarbonInterface $from, CarbonInterface $to, string $label, ?CarbonInterface $createdAt = null): string
    {
        $createdAt ??= now();
        $datev = config('shop.datev');
        $fiscalYearStart = $from->copy()->setMonth((int) $datev['fiscal_year_start_month'])->startOfMonth();

        if ($fiscalYearStart->greaterThan($from)) {
            $fiscalYearStart = $fiscalYearStart->subYear();
        }

        $header = [
            '"EXTF"', 700, 21, '"Buchungsstapel"', 13,
            $createdAt->format('YmdHis').substr($createdAt->format('v'), 0, 3),
            '', '"RE"', $this->quote(config('app.name')), '',
            (string) $datev['consultant_number'], (string) $datev['client_number'],
            $fiscalYearStart->format('Ymd'), (int) $datev['account_length'],
            $from->format('Ymd'), $to->format('Ymd'),
            $this->quote($label), '', 1, '', $datev['festschreibung'] ? 1 : 0, '"EUR"',
            '', '', '', '', $this->quote((string) $datev['skr']), '', '', '', '',
        ];

        $rows = [implode(';', $header), implode(';', self::COLUMNS)];

        foreach ($bookings as $booking) {
            $rows[] = implode(';', array_map(fn (string $column) => $this->cell($column, $booking[$column] ?? null), self::COLUMNS));
        }

        return mb_convert_encoding(implode("\r\n", $rows)."\r\n", 'Windows-1252', 'UTF-8');
    }

    private function cell(string $column, mixed $value): string
    {
        $isText = in_array($column, self::TEXT_COLUMNS, true);

        if ($value === null || $value === '') {
            return $isText ? '""' : '';
        }

        if (in_array($column, self::AMOUNT_COLUMNS, true)) {
            return number_format((float) $value, 2, ',', '');
        }

        return $isText ? $this->quote((string) $value) : (string) $value;
    }

    private function quote(string $value): string
    {
        return '"'.str_replace('"', '""', $value).'"';
    }
}
