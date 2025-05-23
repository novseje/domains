
Add the crontab job:

```bash
# Restart Nginx if needed
*/1 * * * * [ -f /etc/nginx/domains/nginx-need-reload ] && sudo /usr/sbin/nginx -s reload && sudo rm /etc/nginx/domains/nginx-need-reload >/dev/null 2>&1
```