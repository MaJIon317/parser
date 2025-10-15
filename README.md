php artisan queue:work --queue=high,default --sleep=3 --tries=3 --max-time=360000 --timeout=3000
