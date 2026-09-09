<!doctype html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Pointage QR</title>
<style>body{font-family:Arial,sans-serif;background:linear-gradient(135deg,#1a2a6c,#28669e);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}.box{max-width:450px;width:100%;background:#fff;border-radius:22px;padding:30px;box-shadow:0 20px 60px #0004}h1{text-align:center;color:#1a2a6c}label{display:block;font-weight:bold;margin:18px 0 7px;color:#1a2a6c}input{width:100%;padding:13px;border:2px solid #e8edf5;border-radius:10px;box-sizing:border-box;font-size:16px}button{width:100%;padding:15px;margin-top:20px;background:#28669e;color:#fff;border:0;border-radius:10px;font-size:17px;font-weight:bold}#message{display:none;margin-top:18px;padding:12px;border-radius:10px;text-align:center}.success{display:block!important;background:#d4edda;color:#155724}.error{display:block!important;background:#f8d7da;color:#721c24}</style></head>
<body><div class="box"><h1>Pointage par QR Code</h1><p style="text-align:center;color:#6b7a8f">Saisissez votre matricule, téléphone ou email.</p>
<form id="attendanceForm">@csrf<input type="hidden" name="token" value="{{ $token }}"><label>Identifiant employé</label><input name="employee_id" required autocomplete="off"><label>Photo obligatoire</label><input type="file" name="photo" accept="image/*" capture="user" required><button>Enregistrer ma présence</button></form><div id="message"></div></div>
<script>
document.getElementById('attendanceForm').addEventListener('submit', async event => {
 event.preventDefault(); const form=event.currentTarget, message=document.getElementById('message'), data=new FormData(form);
 const photo=form.querySelector('input[type=file]').files[0]; if(photo){data.append('photo',photo);}
 try { const response=await fetch(@json(route('attendance.qr.process')), {method:'POST',body:data,headers:{'Accept':'application/json'}}); const result=await response.json(); message.textContent=result.message||'Une erreur est survenue.'; message.className=result.success?'success':'error'; if(result.success) form.querySelector('button').disabled=true; } catch(error) { message.textContent='Impossible de contacter le serveur.'; message.className='error'; }
});
</script></body></html>
