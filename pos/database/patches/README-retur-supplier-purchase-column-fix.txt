Fix Retur Supplier - kolom purchases

Masalah:
create.php mengambil kolom purchases.invoice_number, sedangkan struktur database project ini tidak punya kolom tersebut.

Perbaikan:
- Referensi pembelian sekarang otomatis mendeteksi kolom yang tersedia:
  invoice_number, purchase_number, reference_number, ref_number, code, number.
- Jika tidak ada semua, fallback memakai PO-ID.
- Total pembelian juga otomatis mendeteksi grand_total / total_amount / total / subtotal.
