import asyncio
import os
import sys
import logging
from email import policy
from email.parser import BytesParser
import requests
from aiosmtpd.controller import Controller

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(name)s: %(message)s",
    handlers=[logging.StreamHandler(sys.stdout)],
)
logger = logging.getLogger("TMail-SMTP")

DELIVERY_URL = os.getenv(
    "DELIVERY_URL",
    "http://app/api/delivery/message/store/mjNSR9FHpY7Q5B3VMK8hADXvLObI1qZa",
)
SMTP_HOST = os.getenv("SMTP_HOST", "0.0.0.0")
SMTP_PORT = int(os.getenv("SMTP_PORT", "25"))
ALLOWED_DOMAINS_ENV = os.getenv("ALLOWED_DOMAINS", "")
ALLOWED_DOMAINS = [
    d.strip().lower() for d in ALLOWED_DOMAINS_ENV.split(",") if d.strip()
]

def extract_email_address(addr_str):
    if not addr_str:
        return ""
    if "<" in addr_str and ">" in addr_str:
        return addr_str.split("<")[1].split(">")[0].strip()
    return addr_str.strip()

class TMailHandler:
    async def handle_RCPT(self, server, session, envelope, address, rcpt_options):
        clean_addr = extract_email_address(address).lower()
        if ALLOWED_DOMAINS:
            domain = clean_addr.split("@")[-1] if "@" in clean_addr else ""
            if domain not in ALLOWED_DOMAINS:
                logger.warning(
                    f"Rejected RCPT TO <{address}>: domain '{domain}' not in allowed list {ALLOWED_DOMAINS}"
                )
                return "550 5.7.1 Relay access denied"

        envelope.rcpt_tos.append(address)
        logger.info(f"Accepted RCPT TO <{address}>")
        return "250 2.1.5 Recipient OK"

    async def handle_DATA(self, server, session, envelope):
        peer = session.peer
        logger.info(
            f"Incoming email from {peer[0]}:{peer[1]} - MAIL FROM: <{envelope.mail_from}>, RCPT TO: {envelope.rcpt_tos}"
        )

        try:
            raw_bytes = envelope.content
            msg = BytesParser(policy=policy.default).parsebytes(raw_bytes)

            subject = msg.get("subject", "(No Subject)")
            from_hdr = msg.get("from", envelope.mail_from or "unknown@sender")

            # Extract body
            text_body = ""
            html_body = ""

            try:
                body_part = msg.get_body(preferencelist=('html', 'plain'))
                if body_part:
                    content_type = body_part.get_content_type()
                    content = body_part.get_content()
                    if content_type == 'text/html':
                        html_body = content
                    else:
                        text_body = content
            except Exception as e:
                logger.warning(f"Error in get_body: {e}")

            # Fallback manual iteration if body not found
            if not html_body and not text_body:
                if msg.is_multipart():
                    for part in msg.walk():
                        ctype = part.get_content_type()
                        cdispo = str(part.get('Content-Disposition', ''))
                        if 'attachment' in cdispo:
                            continue
                        if ctype == 'text/html' and not html_body:
                            html_body = part.get_content()
                        elif ctype == 'text/plain' and not text_body:
                            text_body = part.get_content()
                else:
                    ctype = msg.get_content_type()
                    if ctype == 'text/html':
                        html_body = msg.get_content()
                    else:
                        text_body = msg.get_content()

            # Ensure both exist
            if not html_body and text_body:
                escaped_text = (
                    text_body.replace("&", "&amp;")
                    .replace("<", "&lt;")
                    .replace(">", "&gt;")
                )
                html_body = f"<pre style='font-family: sans-serif; white-space: pre-wrap;'>{escaped_text}</pre>"
            elif not text_body and html_body:
                text_body = html_body

            if not html_body and not text_body:
                text_body = "(Empty Message)"
                html_body = "<p>(Empty Message)</p>"

            success_count = 0
            for rcpt in envelope.rcpt_tos:
                clean_rcpt = extract_email_address(rcpt)
                payload = {
                    "subject": str(subject),
                    "from": str(from_hdr),
                    "to": str(clean_rcpt),
                    "html": str(html_body),
                    "text": str(text_body),
                }

                logger.info(f"Delivering to TMail API: {clean_rcpt} | Subject: '{subject}'")
                try:
                    resp = requests.post(DELIVERY_URL, json=payload, timeout=10)
                    if resp.status_code in (200, 201):
                        logger.info(f"Successfully stored message for {clean_rcpt}: {resp.text}")
                        success_count += 1
                    else:
                        logger.error(
                            f"TMail API error ({resp.status_code}) for {clean_rcpt}: {resp.text}"
                        )
                except Exception as ex:
                    logger.error(f"Failed to post to TMail Delivery API for {clean_rcpt}: {ex}")

            if success_count > 0 or not envelope.rcpt_tos:
                return "250 2.0.0 Message accepted for delivery"
            else:
                return "451 4.3.0 Temporary delivery failure, please retry later"

        except Exception as e:
            logger.error(f"Unhandled error in handle_DATA: {e}", exc_info=True)
            return "451 4.3.0 Internal error processing message"

def main():
    logger.info(f"Starting TMail SMTP Receiver on {SMTP_HOST}:{SMTP_PORT}...")
    logger.info(f"Delivery endpoint: {DELIVERY_URL}")
    if ALLOWED_DOMAINS:
        logger.info(f"Allowed recipient domains: {ALLOWED_DOMAINS}")
    else:
        logger.info("Accepting all recipient domains (Catch-All mode)")

    handler = TMailHandler()
    controller = Controller(
        handler,
        hostname=SMTP_HOST,
        port=SMTP_PORT,
        ready_timeout=15.0,
        enable_SMTPUTF8=True,
    )
    controller.start()
    logger.info("SMTP Receiver is running and listening for incoming mail.")

    try:
        asyncio.get_event_loop().run_forever()
    except (KeyboardInterrupt, SystemExit):
        pass
    finally:
        controller.stop()
        logger.info("SMTP Receiver stopped.")

if __name__ == "__main__":
    main()
