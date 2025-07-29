# 🔧 Fix Hostinger Email Spam Blocks - Complete Guide

## 🚨 Step 1: Re-enable Email Sending in Hostinger

1. **Log into Hostinger Panel**: [hostinger.com](https://hostinger.com)
2. **Navigate**: Emails section → **Manage**
3. **Click**: Email Accounts (left sidebar)
4. **Find**: quotes@carpetree.com → Click **ellipsis (⋮)** → **Settings**
5. **Toggle ON**: Email sending
6. **Click**: Update

## 📧 Step 2: Add Email Authentication (Prevents Future Blocks)

### Add SPF Record:
1. **Go to**: DNS/Nameservers section in Hostinger
2. **Add TXT Record**:
   - **Name**: `@` (or leave blank)
   - **Value**: `v=spf1 include:spf.mail.me.com include:_spf.google.com ~all`
   - **TTL**: 3600

### Add DMARC Record:
1. **Add another TXT Record**:
   - **Name**: `_dmarc`
   - **Value**: `v=DMARC1; p=quarantine; rua=mailto:phil.bajenski@gmail.com`
   - **TTL**: 3600

## ⚙️ Step 3: Email Best Practices (Avoid Future Blocks)

### ✅ What I've Already Improved:
- Removed excessive emojis from subject lines
- Changed "System" to more natural sender name
- Added proper email headers
- Reduced "spammy" language

### 🎯 Hostinger Compliance Tips:
1. **Low Volume**: Only 1 email per quote (not bulk mailing)
2. **Legitimate Purpose**: Transactional business emails (✅)
3. **Clean Lists**: Only sending to actual customers (✅)
4. **No Marketing**: Avoid promotional language
5. **Professional Content**: Business correspondence only (✅)

## 🔍 Step 4: Test Email Delivery

Once you've re-enabled emails in Hostinger:

1. **Submit a test quote**
2. **Check your Gmail inbox** (should arrive in main inbox now)
3. **Verify no spam folder delivery**

## 🛡️ Step 5: Monitor and Maintain

### Watch for these warning signs:
- Emails going to spam again
- Bounced emails
- Delivery delays

### If problems persist:
- Contact Hostinger support
- Consider using external SMTP (SendGrid, Mailgun)
- Implement email queuing system

## 📞 Hostinger Support

If re-enabling doesn't work:
- **Live Chat**: Available 24/7 in Hostinger panel
- **Explain**: "Need to re-enable transactional emails for business quote system"
- **Mention**: "Not bulk marketing, just customer notifications"

---

**After following these steps, your professional carpetree.com emails should work reliably!** 