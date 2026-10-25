// Solo rifinitura dell'interfaccia: l'autorizzazione viene sempre verificata in PHP.
const access = window.icbrAccess;
if (access && !access.admin) {
    const allowed = permission => !permission || access.permissions.includes(permission);
    const required = path => Object.entries(access.routes).find(([route])=>path.endsWith(route))?.[1];
    document.querySelectorAll('a[href]').forEach(a=>{
        const url=new URL(a.href,location.href);
        if (url.origin===location.origin && !allowed(required(url.pathname))) a.hidden=true;
    });
    document.querySelectorAll('form').forEach(form=>{
        if ((form.method || 'get').toLowerCase()!=='post') return;
        const path=new URL(form.action || location.href,location.href).pathname;
        let rule=required(path);
        if (path.endsWith('/messaggi/list.php') || access.current==='/messaggi/list.php' && form.getAttribute('action')===null) {
            form.querySelectorAll('button[name="azione"]').forEach(button=>{
                if (!allowed(button.value==='elimina' ? 'whatsapp.delete' : 'whatsapp.write')) button.hidden=true;
            });
            const action=form.querySelector('input[name="azione"]')?.value;
            if (action && !allowed(action==='elimina'?'whatsapp.delete':'whatsapp.write')) form.hidden=true;
            return;
        }
        if (path.endsWith('/richieste/view.php')) rule=form.querySelector('[name="azione"]')?.value==='elimina'?'richieste.delete':'richieste.write';
        if (!allowed(rule)) form.hidden=true;
    });
    const config=document.querySelector('.nav-dropdown');
    if (config && !access.permissions.includes('configurazione.view')) config.hidden=true;
}
const adminGrant=document.getElementById('amministratore');
if (adminGrant) {
    const update=()=>{
        document.querySelector('.permission-grid')?.classList.toggle('full-admin',adminGrant.checked);
        document.querySelectorAll('.permission-grid input').forEach(input=>input.disabled=adminGrant.checked);
    };
    adminGrant.addEventListener('change',update); update();
}
