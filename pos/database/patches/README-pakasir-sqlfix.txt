Fix SQLSTATE[21S01] Column count doesn't match value count.
File fixed: public/admin/pos/index.php
Cause: INSERT transactions had 13 columns but only 12 VALUES because transaction_date placeholder was missing.
