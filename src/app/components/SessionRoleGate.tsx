import { useLanguage } from '../i18n/useLanguage';
import {useEffect,useState,type ReactNode} from 'react';
import {Navigate} from 'react-router-dom';
import {request} from '../../lib/api';
export default function SessionRoleGate({role,children}:{role:'buyer'|'kadiwa';children:ReactNode}){
 const { lang } = useLanguage();
 const tr = (en:string,fil:string) => lang === 'fil' ? fil : en;
 const [actual,setActual]=useState<string|null>(null),[error,setError]=useState(false);
 useEffect(()=>{let active=true;request('/api/session').then(r=>{if(!active)return;const routes:Record<string,string>={'KADIWA Manager':'kadiwa',Buyer:'buyer',Admin:'admin','Super Admin':'superadmin',Farmer:'producer',Fisherfolk:'producer',Producer:'producer'};const current=routes[r.user_type]||'login';try { localStorage.setItem('userRole',current); } catch { /* The verified server role remains authoritative. */ }setActual(current);}).catch(()=>{if(active)setError(true);});return()=>{active=false;};},[]);
 if(error)return <main className="p-6 text-foreground"><p role="alert">{tr('Your session could not be verified. Please sign in again.', 'Hindi masuri ang iyong session. Mag-sign in muli.')}</p><a href="/login" className="underline">{tr('Sign in', 'Mag-sign in')}</a></main>;
 if(actual===null)return <p role="status" className="p-6">{tr('Verifying your workspace…', 'Sinusuri ang iyong workspace…')}</p>;
 if(actual!==role)return <Navigate to={'/'+actual} replace/>;
 return <>{children}</>;
}