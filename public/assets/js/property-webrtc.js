(function(global){
  'use strict';
  class PropertyHubWhepPlayer {
    constructor(video){this.video=video;this.pc=null;this.hls=null;this.sessionUrl='';this.auth='';this.closed=true;this.lastPlayError='';this._tapHandler=null;this.transport='';}
    emit(phase,value){try{this.video.dispatchEvent(new CustomEvent('ph-webrtc-state',{detail:{phase,value}}));}catch(e){}}
    emitError(message){try{this.video.dispatchEvent(new CustomEvent('ph-webrtc-error',{detail:message}));}catch(e){}}
    emitNeedsPlay(message){try{this.video.dispatchEvent(new CustomEvent('ph-webrtc-needs-play',{detail:message||'Toque para reproducir el video.'}));}catch(e){}}
    prepareVideo(){
      const v=this.video;
      v.muted=true;v.defaultMuted=true;v.autoplay=true;v.playsInline=true;v.controls=true;
      try{v.setAttribute('muted','');v.setAttribute('autoplay','');v.setAttribute('playsinline','');v.setAttribute('webkit-playsinline','');}catch(e){}
      if(this._tapHandler){v.removeEventListener('click',this._tapHandler);this._tapHandler=null;}
      this._tapHandler=()=>{this.requestPlay(true);};v.addEventListener('click',this._tapHandler,{passive:true});
    }
    async requestPlay(fromGesture=false){
      try{
        const p=this.video.play();if(p&&typeof p.then==='function')await p;
        this.lastPlayError='';this.emit('playback','playing');return true;
      }catch(e){
        this.lastPlayError=(e&&e.message)?e.message:String(e||'play() rechazado');
        this.emit('playback','blocked');
        if(!fromGesture)this.emitNeedsPlay(this.lastPlayError);
        else this.emitError('El navegador no permitió reproducir: '+this.lastPlayError);
        return false;
      }
    }
    isIOS(){const ua=navigator.userAgent||'';return /iPad|iPhone|iPod/.test(ua)||(navigator.platform==='MacIntel'&&navigator.maxTouchPoints>1);}
    isMobile(){const ua=navigator.userAgent||'';return this.isIOS()||/Android|Mobile/i.test(ua);}
    isQuickTunnel(url){try{return String(new URL(String(url||''),location.href).hostname||'').toLowerCase().endsWith('.trycloudflare.com');}catch(e){return /\.trycloudflare\.com(?=[:/]|$)/i.test(String(url||''));}}
    basicAuth(cfg,kind='whep'){const user=kind==='hls'?String(cfg?.hls_user||''):String(cfg?.whep_user||cfg?.user||'');const pass=kind==='hls'?String(cfg?.hls_pass||''):String(cfg?.whep_pass||cfg?.pass||'');return user?('Basic '+btoa(user+':'+pass)):'';}

    async startHls(cfg){
      if(!cfg||!cfg.hls_url)throw new Error('No se recibió URL HLS.');
      const v=this.video;this.transport='hls';this.prepareVideo();this.auth=this.basicAuth(cfg,'hls');
      try{v.srcObject=null;}catch(e){}
      const nativeHls=!this.auth&&v.canPlayType&&v.canPlayType('application/vnd.apple.mpegurl');
      if(nativeHls){
        v.src=cfg.hls_url;this.emit('transport','hls-native');
        await new Promise((resolve,reject)=>{let done=false;const finish=(ok,err)=>{if(done)return;done=true;clearTimeout(timer);v.removeEventListener('loadedmetadata',loaded);v.removeEventListener('error',failed);ok?resolve():reject(err);};const loaded=()=>finish(true);const failed=()=>finish(false,new Error('HLS nativo no pudo cargar desde el Node.'));v.addEventListener('loadedmetadata',loaded,{once:true});v.addEventListener('error',failed,{once:true});const timer=setTimeout(()=>finish(false,new Error('HLS nativo agotó el tiempo de espera.')),20000);try{v.load();}catch(e){}});
        this.emit('hls','ready');this.emit('track','video');await this.requestPlay(false);return {ok:true,transport:'hls-native',needs_user_play:this.lastPlayError!==''};
      }
      if(global.Hls&&typeof global.Hls.isSupported==='function'&&global.Hls.isSupported()){
        const auth=this.auth;
        const hls=new global.Hls({lowLatencyMode:true,backBufferLength:15,maxBufferLength:12,xhrSetup:function(xhr){if(auth)xhr.setRequestHeader('Authorization',auth);}});
        this.hls=hls;
        await new Promise((resolve,reject)=>{
          let done=false;
          const finish=(ok,err)=>{if(done)return;done=true;clearTimeout(timer);hls.off(global.Hls.Events.MANIFEST_PARSED,onManifest);hls.off(global.Hls.Events.ERROR,onError);ok?resolve():reject(err);};
          const onManifest=()=>finish(true);
          const onError=(event,data)=>{if(data&&data.fatal)finish(false,new Error('HLS '+String(data.details||data.type||'fatal')));};
          hls.on(global.Hls.Events.MANIFEST_PARSED,onManifest);hls.on(global.Hls.Events.ERROR,onError);
          const timer=setTimeout(()=>finish(false,new Error('HLS agotó el tiempo de espera.')),18000);
          hls.attachMedia(v);hls.on(global.Hls.Events.MEDIA_ATTACHED,()=>{try{hls.loadSource(cfg.hls_url);}catch(e){finish(false,e);}});
        });
        this.emit('transport','hls');this.emit('hls','ready');this.emit('track','video');
        await this.requestPlay(false);
        return {ok:true,transport:'hls',needs_user_play:this.lastPlayError!==''};
      }
      throw new Error('Este navegador no dispone de reproducción HLS compatible.');
    }

    async waitConnected(pc,timeout=8500){
      const good=()=>pc.connectionState==='connected'||pc.iceConnectionState==='connected'||pc.iceConnectionState==='completed';
      if(good())return true;
      return new Promise(resolve=>{
        let done=false;
        const finish=(value)=>{if(done)return;done=true;clearTimeout(timer);pc.removeEventListener('connectionstatechange',check);pc.removeEventListener('iceconnectionstatechange',check);resolve(value);};
        const check=()=>{if(good())finish(true);else if(['failed','closed'].includes(pc.connectionState)||['failed','closed'].includes(pc.iceConnectionState))finish(false);};
        pc.addEventListener('connectionstatechange',check);pc.addEventListener('iceconnectionstatechange',check);
        const timer=setTimeout(()=>finish(good()),timeout);
      });
    }

    async startWebRtc(cfg){
      if(!cfg||!cfg.whep_url)throw new Error('No se recibió URL WHEP.');
      this.transport='webrtc';this.prepareVideo();
      this.auth=this.basicAuth(cfg,'whep');
      const pc=new RTCPeerConnection({iceServers:[{urls:'stun:stun.l.google.com:19302'}]});this.pc=pc;
      const media=new MediaStream();this.video.srcObject=media;
      let videoTrackSeen=false,resolveVideoTrack=null;const videoTrackPromise=new Promise(resolve=>{resolveVideoTrack=resolve;});
      pc.addTransceiver('video',{direction:'recvonly'});
      if(cfg.allow_audio)pc.addTransceiver('audio',{direction:'recvonly'});
      pc.ontrack=(ev)=>{
        if(ev.streams&&ev.streams[0])this.video.srcObject=ev.streams[0];else media.addTrack(ev.track);
        this.emit('track',ev.track?.kind||'media');
        if(ev.track&&ev.track.kind==='video'){
          videoTrackSeen=true;if(resolveVideoTrack)resolveVideoTrack(true);
          this.video.style.display='block';
          const play=()=>this.requestPlay(false);
          if(this.video.readyState>=1)play();else this.video.addEventListener('loadedmetadata',play,{once:true});
        }
      };
      pc.oniceconnectionstatechange=()=>{this.emit('ice',pc.iceConnectionState);if(['failed','disconnected'].includes(pc.iceConnectionState)&&!this.closed)this.emitError('ICE '+pc.iceConnectionState+'.');};
      pc.onconnectionstatechange=()=>{this.emit('peer',pc.connectionState);if(['failed','disconnected'].includes(pc.connectionState)&&!this.closed)this.emitError('Conexión WebRTC '+pc.connectionState+'.');};
      const offer=await pc.createOffer();await pc.setLocalDescription(offer);this.emit('signaling','offer-created');await this.waitIce(pc,5000);this.emit('signaling','ice-gathered');
      const headers={'Content-Type':'application/sdp'};if(this.auth)headers.Authorization=this.auth;
      const res=await fetch(cfg.whep_url,{method:'POST',headers,body:pc.localDescription.sdp,cache:'no-store',mode:'cors'});this.emit('whep','http-'+res.status);
      if(!res.ok){let text='';try{text=(await res.text()).trim();}catch(e){}throw new Error('WHEP respondió HTTP '+res.status+(text?' · '+text.slice(0,180):''));}
      const answer=await res.text();if(!answer.trim())throw new Error('El Media Relay devolvió una respuesta SDP vacía.');
      const location=res.headers.get('Location');if(location){try{this.sessionUrl=new URL(location,cfg.whep_url).toString();}catch(e){this.sessionUrl='';}}
      await pc.setRemoteDescription({type:'answer',sdp:answer});this.emit('signaling','answer-applied');
      const connected=await this.waitConnected(pc,8500);
      if(!connected)throw new Error('WHEP respondió, pero ICE no estableció el canal de medios.');
      if(!videoTrackSeen){const got=await Promise.race([videoTrackPromise,new Promise(resolve=>setTimeout(()=>resolve(false),4000))]);if(!got)throw new Error('WebRTC conectó por ICE pero no recibió una pista de video.');}
      await this.requestPlay(false);
      return {ok:true,transport:'webrtc',needs_user_play:this.lastPlayError!==''};
    }

    async start(cfg){
      await this.close();this.closed=false;
      const hasHls=!!(cfg&&cfg.hls_url);
      const quickTunnel=!!(cfg&&cfg.whep_url&&this.isQuickTunnel(cfg.whep_url));
      const preferHls=hasHls&&(!!cfg?.prefer_hls||quickTunnel||this.isMobile());
      this.emit('preference',quickTunnel&&hasHls?'quick-tunnel-hls-first':(preferHls?'mobile-hls-first':'desktop-webrtc-first'));

      if(preferHls){
        try{return await this.startHls(cfg);}catch(e){
          this.emitError('HLS seguro: '+(e&&e.message?e.message:e));
          await this.close();this.closed=false;this.emit('hls','fallback-webrtc');
        }
      }

      try{return await this.startWebRtc(cfg);}catch(webrtcError){
        this.emitError('WebRTC: '+(webrtcError&&webrtcError.message?webrtcError.message:webrtcError));
        if(hasHls&&!preferHls){
          await this.close();this.closed=false;this.emit('webrtc','fallback-hls');
          try{return await this.startHls(cfg);}catch(hlsError){
            throw new Error('WebRTC falló: '+(webrtcError&&webrtcError.message?webrtcError.message:webrtcError)+' · HLS falló: '+(hlsError&&hlsError.message?hlsError.message:hlsError));
          }
        }
        throw webrtcError;
      }
    }
    waitIce(pc,timeout){if(pc.iceGatheringState==='complete')return Promise.resolve();return new Promise((resolve)=>{let done=false;const finish=()=>{if(done)return;done=true;pc.removeEventListener('icegatheringstatechange',change);clearTimeout(timer);resolve();};const change=()=>{if(pc.iceGatheringState==='complete')finish();};pc.addEventListener('icegatheringstatechange',change);const timer=setTimeout(finish,timeout);});}
    async close(){
      this.closed=true;const session=this.sessionUrl;const auth=this.auth;this.sessionUrl='';this.auth='';this.lastPlayError='';
      if(this._tapHandler&&this.video){try{this.video.removeEventListener('click',this._tapHandler);}catch(e){}this._tapHandler=null;}
      if(this.hls){try{this.hls.destroy();}catch(e){}this.hls=null;}
      if(this.pc){try{this.pc.ontrack=null;this.pc.close();}catch(e){}this.pc=null;}
      if(this.video){try{const stream=this.video.srcObject;if(stream&&stream.getTracks)stream.getTracks().forEach(t=>t.stop());}catch(e){}try{this.video.pause();}catch(e){}this.video.srcObject=null;try{this.video.removeAttribute('src');this.video.load();}catch(e){}}
      this.transport='';
      if(session){try{const headers={};if(auth)headers.Authorization=auth;await fetch(session,{method:'DELETE',headers,keepalive:true,mode:'cors'});}catch(e){}}
    }
  }
  global.PropertyHubWhepPlayer=PropertyHubWhepPlayer;
})(window);
