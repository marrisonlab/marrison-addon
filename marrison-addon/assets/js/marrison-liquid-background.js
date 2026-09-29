( function () {
	'use strict';

	var selector = '[data-marrison-liquid-background]';
	var dataName = 'data-marrison-liquid-background';
	var instances = new Map();
	var reducedMotion = window.matchMedia ? window.matchMedia( '(prefers-reduced-motion: reduce)' ) : { matches: false };
	var renderFrame = 0;
	var syncFrame = 0;
	var lastFrameTime = 0;
	var elementorHookRegistered = false;
	var elementorHandlerRegistered = false;
	var targetFrameTime = 1000 / 40;

	var presets = {
		'deep-purple': [ '#000000', '#6C3BFF', '#321875' ],
		'blue-ink': [ '#020712', '#2869DA', '#102C77' ],
		'monochrome': [ '#000000', '#D9DCE5', '#555B69' ]
	};

	var vertexSource = [
		'attribute vec2 a_position;',
		'varying vec2 v_uv;',
		'void main(){',
		'v_uv=a_position*0.5+0.5;',
		'gl_Position=vec4(a_position,0.0,1.0);',
		'}'
	].join( '' );

	var fragmentSource = [
		'precision mediump float;',
		'varying vec2 v_uv;',
		'uniform vec2 u_resolution;',
		'uniform vec2 u_mouse;',
		'uniform vec3 u_background;',
		'uniform vec3 u_primary;',
		'uniform vec3 u_secondary;',
		'uniform float u_has_secondary;',
		'uniform float u_time;',
		'uniform float u_scale;',
		'uniform float u_complexity;',
		'uniform float u_distortion;',
		'uniform float u_softness;',
		'uniform float u_contrast;',
		'uniform float u_opacity;',
		'uniform float u_mouse_influence;',
		'uniform float u_direction;',
		'uniform float u_seed;',
		'float hash(vec2 p){',
		'p=fract(p*vec2(123.34,456.21));',
		'p+=dot(p,p+45.32);',
		'return fract(p.x*p.y);',
		'}',
		'float noise(vec2 p){',
		'vec2 i=floor(p);',
		'vec2 f=fract(p);',
		'vec2 u=f*f*(3.0-2.0*f);',
		'float a=hash(i);',
		'float b=hash(i+vec2(1.0,0.0));',
		'float c=hash(i+vec2(0.0,1.0));',
		'float d=hash(i+vec2(1.0,1.0));',
		'return mix(mix(a,b,u.x),mix(c,d,u.x),u.y);',
		'}',
		'float fbm(vec2 p,float octaves){',
		'float v=0.0;',
		'float a=0.55;',
		'for(int i=0;i<4;i++){',
		'if(float(i)<octaves){v+=a*noise(p);}',
		'p=p*2.03+vec2(17.1,9.2);',
		'a*=0.5;',
		'}',
		'return v;',
		'}',
		'void main(){',
		'vec2 uv=v_uv;',
		'vec2 p=uv-0.5;',
		'p.x*=u_resolution.x/max(u_resolution.y,1.0);',
		'float seed=u_seed*0.013;',
		'vec2 natural=vec2(cos(u_time*0.19+seed),sin(u_time*0.16-seed));',
		'vec2 horizontal=vec2(sin(u_time*0.22+seed),0.18*cos(u_time*0.11));',
		'vec2 vertical=vec2(0.18*sin(u_time*0.13),cos(u_time*0.21-seed));',
		'vec2 drift=mix(natural,horizontal,step(0.5,u_direction));',
		'drift=mix(drift,vertical,step(1.5,u_direction));',
		'p=p*(1.18/max(u_scale,0.001))+drift*0.18;',
		'vec2 mouse=(u_mouse-0.5)*vec2(u_resolution.x/max(u_resolution.y,1.0),1.0);',
		'float mouse_field=exp(-dot(p-mouse,p-mouse)*2.8)*u_mouse_influence;',
		'p+=vec2(noise(p*1.7+seed),noise(p*1.7-seed))-0.5;',
		'p+=normalize(p-mouse+vec2(0.001))*mouse_field*0.22;',
		'vec2 warp=vec2(',
		'fbm(p*1.55+vec2(seed,u_time*0.09),u_complexity),',
		'fbm(p*1.55+vec2(-seed,u_time*-0.07),u_complexity)',
		')-0.5;',
		'vec2 q=p+warp*u_distortion*2.25;',
		'float broad=fbm(q*0.72-drift*0.28+seed,u_complexity);',
		'float detail=fbm(q*1.36+drift*0.18-seed,u_complexity);',
		'float field=(broad*0.68+detail*0.52);',
		'field=(field-0.5)*u_contrast+0.5;',
		'float edge=mix(0.035,0.24,u_softness);',
		'float mask=smoothstep(0.47-edge,0.47+edge,field);',
		'mask*=smoothstep(0.08,0.9,fbm(q*0.42+seed*0.7,u_complexity));',
		'float tint=fbm(q*0.95-warp+seed*0.31,u_complexity);',
		'float ribbon=fbm(q*0.58+warp*0.35+seed*0.61,u_complexity);',
		'float secondary_blend=smoothstep(0.36,0.72,tint*0.7+ribbon*0.3);',
		'secondary_blend*=smoothstep(0.08,0.48,mask);',
		'vec3 liquid=mix(u_primary,u_secondary,u_has_secondary*secondary_blend);',
		'vec3 color=mix(u_background,liquid,mask);',
		'gl_FragColor=vec4(color,u_opacity);',
		'}'
	].join( '' );

	function own( object, key ) {
		return Object.prototype.hasOwnProperty.call( object, key );
	}

	function clamp( value, min, max ) {
		return Math.max( min, Math.min( max, value ) );
	}

	function number( value, min, max, fallback ) {
		var raw = value && typeof value === 'object' && own( value, 'size' ) ? value.size : value;
		var parsed = parseFloat( raw );
		return isFinite( parsed ) ? clamp( parsed, min, max ) : fallback;
	}

	function bool( value ) {
		return value === true || value === 'yes' || value === '1' || value === 1;
	}

	function validColor( value, fallback, allowEmpty ) {
		if ( value === '' && allowEmpty ) {
			return '';
		}
		if ( typeof value === 'string' && /^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test( value ) ) {
			return value;
		}
		return fallback;
	}

	function colorToVector( value, fallback ) {
		var color = validColor( value, fallback || '#000000', false ).slice( 1 );
		if ( color.length === 3 ) {
			color = color.charAt( 0 ) + color.charAt( 0 ) + color.charAt( 1 ) + color.charAt( 1 ) + color.charAt( 2 ) + color.charAt( 2 );
		}
		return [
			parseInt( color.slice( 0, 2 ), 16 ) / 255,
			parseInt( color.slice( 2, 4 ), 16 ) / 255,
			parseInt( color.slice( 4, 6 ), 16 ) / 255
		];
	}

	function colorToRgba( value, alpha ) {
		var vector = colorToVector( value, '#000000' );
		return 'rgba(' + Math.round( vector[ 0 ] * 255 ) + ',' + Math.round( vector[ 1 ] * 255 ) + ',' + Math.round( vector[ 2 ] * 255 ) + ',' + clamp( alpha, 0, 1 ) + ')';
	}

	function responsiveValue( values, min, max, fallback ) {
		var desktop;
		var tablet;
		var mobile;

		if ( values && typeof values === 'object' && ( own( values, 'desktop' ) || own( values, 'tablet' ) || own( values, 'mobile' ) ) ) {
			desktop = number( values.desktop, min, max, fallback );
			tablet = number( values.tablet, min, max, desktop );
			mobile = number( values.mobile, min, max, tablet );
			return { desktop: desktop, tablet: tablet, mobile: mobile };
		}

		desktop = number( values, min, max, fallback );
		return { desktop: desktop, tablet: desktop, mobile: desktop };
	}

	function responsiveFromSettings( settings, key, min, max, fallback ) {
		var desktop = number( settings[ key ], min, max, fallback );
		var tablet = number( settings[ key + '_tablet' ], min, max, desktop );
		var mobile = number( settings[ key + '_mobile' ], min, max, tablet );
		return { desktop: desktop, tablet: tablet, mobile: mobile };
	}

	function stableSeed( value ) {
		var text = String( value || 'marrison-liquid' );
		var hash = 2166136261;
		var i;
		for ( i = 0; i < text.length; i++ ) {
			hash ^= text.charCodeAt( i );
			hash += ( hash << 1 ) + ( hash << 4 ) + ( hash << 7 ) + ( hash << 8 ) + ( hash << 24 );
		}
		return Math.abs( hash ) % 100000;
	}

	function readBreakpoints( breakpoints ) {
		var values = { mobile: 767, tablet: 1024 };
		var frontend = window.elementorFrontend;
		var responsive = frontend && frontend.config && frontend.config.responsive;
		var source = breakpoints || ( responsive && responsive.breakpoints );

		if ( source ) {
			if ( source.mobile ) {
				values.mobile = parseInt( source.mobile.value || source.mobile, 10 ) || values.mobile;
			}
			if ( source.tablet ) {
				values.tablet = parseInt( source.tablet.value || source.tablet, 10 ) || values.tablet;
			}
		}

		return values;
	}

	function normalizeConfig( config ) {
		var preset = own( presets, config.preset ) || config.preset === 'custom' ? config.preset : 'deep-purple';
		var presetColors = presets[ preset ] || presets[ 'deep-purple' ];
		var background = validColor( config.background, presetColors[ 0 ], false );
		var primary = validColor( config.primary, presetColors[ 1 ], false );
		var secondary = validColor( config.secondary, presetColors[ 2 ], true );
		var direction = [ 'natural', 'horizontal', 'vertical' ].indexOf( config.direction ) >= 0 ? config.direction : 'natural';

		return {
			preset: preset,
			background: background,
			primary: primary,
			secondary: secondary,
			speed: responsiveValue( config.speed, 0.1, 3, 0.35 ),
			scale: responsiveValue( config.scale, 0.65, 2, 1 ),
			complexity: Math.round( number( config.complexity, 1, 4, 3 ) ),
			distortion: number( config.distortion, 0, 1.5, 0.5 ),
			softness: number( config.softness, 0.1, 1, 0.45 ),
			contrast: number( config.contrast, 0.5, 2, 1 ),
			opacity: responsiveValue( config.opacity, 0, 1, 1 ),
			blendGradient: {
				enabled: bool( config.blendGradient && config.blendGradient.enabled ),
				color: validColor( config.blendGradient && config.blendGradient.color, '#000000', false ),
				height: responsiveValue( config.blendGradient && config.blendGradient.height, 10, 100, 45 )
			},
			mouse: bool( config.mouse ),
			mouseInfluence: number( config.mouseInfluence, 0, 0.5, 0.2 ),
			direction: direction,
			disableMobile: bool( config.disableMobile ),
			reducedMotion: config.reducedMotion === 'disable' ? 'disable' : 'static',
			seed: Math.round( number( config.seed, 0, 99999, 0 ) ),
			breakpoints: readBreakpoints( config.breakpoints )
		};
	}

	function configFromSettings( settings, id ) {
		var preset;
		var colors;
		var seed;

		if ( ! settings || settings.marrison_liquid_enabled !== 'yes' ) {
			return null;
		}

		preset = own( presets, settings.marrison_liquid_preset ) || settings.marrison_liquid_preset === 'custom' ? settings.marrison_liquid_preset : 'deep-purple';
		if ( preset === 'custom' ) {
			colors = [
				validColor( settings.marrison_liquid_background, '#000000', false ),
				validColor( settings.marrison_liquid_primary, '#6C3BFF', false ),
				validColor( own( settings, 'marrison_liquid_secondary' ) ? settings.marrison_liquid_secondary : '#321875', '', true )
			];
		} else {
			colors = presets[ preset ];
		}

		seed = settings.marrison_liquid_seed;
		if ( seed === '' || seed === null || typeof seed === 'undefined' ) {
			seed = stableSeed( id );
		}

		return normalizeConfig( {
			preset: preset,
			background: colors[ 0 ],
			primary: colors[ 1 ],
			secondary: colors[ 2 ],
			speed: responsiveFromSettings( settings, 'marrison_liquid_speed', 0.1, 3, 0.35 ),
			scale: responsiveFromSettings( settings, 'marrison_liquid_scale', 0.65, 2, 1 ),
			complexity: settings.marrison_liquid_complexity,
			distortion: settings.marrison_liquid_distortion,
			softness: settings.marrison_liquid_softness,
			contrast: settings.marrison_liquid_contrast,
			opacity: responsiveFromSettings( settings, 'marrison_liquid_opacity', 0, 1, 1 ),
			blendGradient: {
				enabled: settings.marrison_liquid_blend_gradient,
				color: validColor( settings.marrison_liquid_blend_color, '#000000', false ),
				height: responsiveFromSettings( settings, 'marrison_liquid_blend_height', 10, 100, 45 )
			},
			mouse: settings.marrison_liquid_mouse,
			mouseInfluence: settings.marrison_liquid_mouse_influence,
			direction: settings.marrison_liquid_direction,
			disableMobile: settings.marrison_liquid_disable_mobile,
			reducedMotion: settings.marrison_liquid_reduced_motion,
			seed: seed,
			breakpoints: readBreakpoints()
		} );
	}

	function readConfig( root ) {
		try {
			return normalizeConfig( JSON.parse( root.getAttribute( dataName ) || '' ) );
		} catch ( error ) {
			return null;
		}
	}

	function currentDevice( config ) {
		var width = window.innerWidth || document.documentElement.clientWidth || 1024;
		if ( width <= config.breakpoints.mobile ) {
			return 'mobile';
		}
		if ( width <= config.breakpoints.tablet ) {
			return 'tablet';
		}
		return 'desktop';
	}

	function deviceNumber( config, key ) {
		return responsiveNumberForDevice( config, config[ key ] );
	}

	function responsiveNumberForDevice( config, values ) {
		return values[ currentDevice( config ) ] || values.desktop;
	}

	function directionNumber( direction ) {
		if ( direction === 'horizontal' ) {
			return 1;
		}
		if ( direction === 'vertical' ) {
			return 2;
		}
		return 0;
	}

	function createShader( gl, type, source ) {
		var shader = gl.createShader( type );
		gl.shaderSource( shader, source );
		gl.compileShader( shader );
		if ( ! gl.getShaderParameter( shader, gl.COMPILE_STATUS ) ) {
			gl.deleteShader( shader );
			return null;
		}
		return shader;
	}

	function createProgram( gl ) {
		var vertex = createShader( gl, gl.VERTEX_SHADER, vertexSource );
		var fragment = createShader( gl, gl.FRAGMENT_SHADER, fragmentSource );
		var program;

		if ( ! vertex || ! fragment ) {
			if ( vertex ) {
				gl.deleteShader( vertex );
			}
			if ( fragment ) {
				gl.deleteShader( fragment );
			}
			return null;
		}

		program = gl.createProgram();
		gl.attachShader( program, vertex );
		gl.attachShader( program, fragment );
		gl.linkProgram( program );
		gl.deleteShader( vertex );
		gl.deleteShader( fragment );

		if ( ! gl.getProgramParameter( program, gl.LINK_STATUS ) ) {
			gl.deleteProgram( program );
			return null;
		}

		return program;
	}

	function LiquidBackgroundInstance( root, config, rawConfig ) {
		this.root = root;
		this.config = config;
		this.rawConfig = rawConfig;
		this.canvas = null;
		this.fallback = null;
		this.blend = null;
		this.gl = null;
		this.program = null;
		this.buffer = null;
		this.uniforms = {};
		this.width = 0;
		this.height = 0;
		this.visible = true;
		this.lost = false;
		this.staticMode = false;
		this.positionSnapshot = null;
		this.positionOwned = false;
		this.resizeObserver = null;
		this.intersectionObserver = null;
		this.resizeQueued = true;
		this.mouse = { x: 0.5, y: 0.5 };
		this.mouseListening = false;
		this.boundMouseMove = this.handleMouseMove.bind( this );
		this.boundMouseLeave = this.handleMouseLeave.bind( this );
		this.boundContextLost = this.handleContextLost.bind( this );
		this.boundContextRestored = this.handleContextRestored.bind( this );
		this.init();
	}

	LiquidBackgroundInstance.prototype.init = function () {
		this.createObservers();
		this.applyMode();
	};

	LiquidBackgroundInstance.prototype.createObservers = function () {
		var self = this;

		if ( 'IntersectionObserver' in window ) {
			this.intersectionObserver = new IntersectionObserver( function ( entries ) {
				entries.forEach( function ( entry ) {
					if ( entry.target === self.root ) {
						self.visible = !! entry.isIntersecting;
						if ( self.visible && self.staticMode ) {
							self.render( 0 );
						}
					}
				} );
				ensureLoop();
			} );
			this.intersectionObserver.observe( this.root );
		}

		if ( 'ResizeObserver' in window ) {
			this.resizeObserver = new ResizeObserver( function () {
				self.resizeQueued = true;
				if ( self.staticMode ) {
					self.render( 0 );
				}
				ensureLoop();
			} );
			this.resizeObserver.observe( this.root );
		}
	};

	LiquidBackgroundInstance.prototype.updateConfig = function ( config, rawConfig ) {
		this.config = config;
		this.rawConfig = rawConfig;
		this.resizeQueued = true;
		this.applyMode();
	};

	LiquidBackgroundInstance.prototype.isDisabledByEnvironment = function () {
		return ( this.config.disableMobile && currentDevice( this.config ) === 'mobile' ) ||
			( reducedMotion.matches && this.config.reducedMotion === 'disable' );
	};

	LiquidBackgroundInstance.prototype.applyMode = function () {
		if ( this.isDisabledByEnvironment() ) {
			this.teardownVisual();
			return;
		}

		this.staticMode = reducedMotion.matches && this.config.reducedMotion === 'static';
		if ( ! this.gl && ! this.fallback ) {
			this.setupWebGL();
		}
		this.applyCssVariables();
		this.updateBlendLayer();
		this.updateMouseListeners();

		if ( this.staticMode ) {
			this.render( 0 );
		}

		ensureLoop();
	};

	LiquidBackgroundInstance.prototype.applyCssVariables = function () {
		this.root.style.setProperty( '--marrison-liquid-background', this.config.background );
		this.root.style.setProperty( '--marrison-liquid-primary', this.config.primary );
		this.root.style.setProperty( '--marrison-liquid-secondary', this.config.secondary || this.config.primary );
		this.root.style.setProperty( '--marrison-liquid-opacity', String( deviceNumber( this.config, 'opacity' ) ) );
		this.root.style.setProperty( '--marrison-liquid-blend-color', this.config.blendGradient.color );
		this.root.style.setProperty( '--marrison-liquid-blend-height', responsiveNumberForDevice( this.config, this.config.blendGradient.height ) + '%' );
	};

	LiquidBackgroundInstance.prototype.ensureHostStyle = function () {
		var computed;

		this.root.classList.add( 'marrison-liquid-background-host' );
		computed = window.getComputedStyle ? window.getComputedStyle( this.root ) : null;
		if ( computed && computed.position === 'static' ) {
			this.positionOwned = true;
			this.positionSnapshot = {
				value: this.root.style.getPropertyValue( 'position' ),
				priority: this.root.style.getPropertyPriority( 'position' )
			};
			this.root.style.setProperty( 'position', 'relative' );
		}
	};

	LiquidBackgroundInstance.prototype.restoreHostStyle = function () {
		if ( this.positionOwned ) {
			if ( this.positionSnapshot && this.positionSnapshot.value ) {
				this.root.style.setProperty( 'position', this.positionSnapshot.value, this.positionSnapshot.priority );
			} else {
				this.root.style.removeProperty( 'position' );
			}
		}
		this.positionOwned = false;
		this.positionSnapshot = null;
		this.root.classList.remove( 'marrison-liquid-background-host' );
		this.root.style.removeProperty( '--marrison-liquid-background' );
		this.root.style.removeProperty( '--marrison-liquid-primary' );
		this.root.style.removeProperty( '--marrison-liquid-secondary' );
		this.root.style.removeProperty( '--marrison-liquid-opacity' );
		this.root.style.removeProperty( '--marrison-liquid-blend-color' );
		this.root.style.removeProperty( '--marrison-liquid-blend-height' );
	};

	LiquidBackgroundInstance.prototype.insertLayer = function ( element ) {
		this.ensureHostStyle();
		element.setAttribute( 'aria-hidden', 'true' );
		element.setAttribute( 'tabindex', '-1' );
		this.root.insertBefore( element, this.root.firstChild );
	};

	LiquidBackgroundInstance.prototype.setupWebGL = function () {
		var gl;
		var program;
		var positionLocation;
		var names;
		var i;

		this.teardownVisual();
		this.canvas = document.createElement( 'canvas' );
		this.canvas.className = 'marrison-liquid-background-canvas';
		this.canvas.addEventListener( 'webglcontextlost', this.boundContextLost, false );
		this.canvas.addEventListener( 'webglcontextrestored', this.boundContextRestored, false );
		this.insertLayer( this.canvas );

		try {
			gl = this.canvas.getContext( 'webgl', {
				alpha: true,
				antialias: false,
				depth: false,
				stencil: false,
				premultipliedAlpha: false,
				preserveDrawingBuffer: false,
				powerPreference: 'low-power'
			} );
		} catch ( error ) {
			gl = null;
		}

		if ( ! gl ) {
			this.setupFallback();
			return;
		}

		program = createProgram( gl );
		if ( ! program ) {
			this.setupFallback();
			return;
		}

		this.gl = gl;
		this.program = program;
		this.buffer = gl.createBuffer();
		gl.bindBuffer( gl.ARRAY_BUFFER, this.buffer );
		gl.bufferData( gl.ARRAY_BUFFER, new Float32Array( [ -1, -1, 3, -1, -1, 3 ] ), gl.STATIC_DRAW );
		gl.useProgram( program );
		positionLocation = gl.getAttribLocation( program, 'a_position' );
		gl.enableVertexAttribArray( positionLocation );
		gl.vertexAttribPointer( positionLocation, 2, gl.FLOAT, false, 0, 0 );

		names = [
			'u_resolution', 'u_mouse', 'u_background', 'u_primary', 'u_secondary',
			'u_has_secondary', 'u_time', 'u_scale', 'u_complexity', 'u_distortion',
			'u_softness', 'u_contrast', 'u_opacity', 'u_mouse_influence', 'u_direction', 'u_seed'
		];
		for ( i = 0; i < names.length; i++ ) {
			this.uniforms[ names[ i ] ] = gl.getUniformLocation( program, names[ i ] );
		}

		this.resizeQueued = true;
		this.render( 0 );
	};

	LiquidBackgroundInstance.prototype.setupFallback = function () {
		this.destroyGL( false );
		this.fallback = document.createElement( 'div' );
		this.fallback.className = 'marrison-liquid-background-fallback';
		this.insertLayer( this.fallback );
		this.applyCssVariables();
	};

	LiquidBackgroundInstance.prototype.updateBlendLayer = function () {
		var color = this.config.blendGradient.color;
		var height = responsiveNumberForDevice( this.config, this.config.blendGradient.height );

		if ( ! this.config.blendGradient.enabled ) {
			if ( this.blend ) {
				this.blend.remove();
				this.blend = null;
			}
			return;
		}

		if ( ! this.blend ) {
			this.blend = document.createElement( 'div' );
			this.blend.className = 'marrison-liquid-background-blend';
			this.insertLayer( this.blend );
		}

		this.blend.style.setProperty( 'height', height + '%' );
		this.blend.style.setProperty( 'background', 'linear-gradient(to bottom, ' + colorToRgba( color, 0 ) + ' 0%, ' + colorToRgba( color, 0.86 ) + ' 42%, ' + color + ' 68%, ' + color + ' 100%)' );
	};

	LiquidBackgroundInstance.prototype.destroyGL = function ( keepCanvas ) {
		if ( this.gl ) {
			if ( this.buffer ) {
				this.gl.deleteBuffer( this.buffer );
			}
			if ( this.program ) {
				this.gl.deleteProgram( this.program );
			}
		}
		this.gl = null;
		this.program = null;
		this.buffer = null;
		this.uniforms = {};
		if ( ! keepCanvas && this.canvas ) {
			this.canvas.removeEventListener( 'webglcontextlost', this.boundContextLost, false );
			this.canvas.removeEventListener( 'webglcontextrestored', this.boundContextRestored, false );
			this.canvas.remove();
			this.canvas = null;
		}
	};

	LiquidBackgroundInstance.prototype.teardownVisual = function () {
		this.updateMouseListeners( true );
		this.destroyGL( false );
		if ( this.blend ) {
			this.blend.remove();
			this.blend = null;
		}
		if ( this.fallback ) {
			this.fallback.remove();
			this.fallback = null;
		}
		this.staticMode = false;
		this.restoreHostStyle();
	};

	LiquidBackgroundInstance.prototype.handleContextLost = function ( event ) {
		event.preventDefault();
		this.lost = true;
		ensureLoop();
	};

	LiquidBackgroundInstance.prototype.handleContextRestored = function () {
		this.lost = false;
		this.destroyGL( true );
		this.setupWebGL();
		this.applyMode();
	};

	LiquidBackgroundInstance.prototype.handleMouseMove = function ( event ) {
		var rect = this.root.getBoundingClientRect();
		if ( rect.width > 0 && rect.height > 0 ) {
			this.mouse.x = clamp( ( event.clientX - rect.left ) / rect.width, 0, 1 );
			this.mouse.y = clamp( 1 - ( event.clientY - rect.top ) / rect.height, 0, 1 );
		}
	};

	LiquidBackgroundInstance.prototype.handleMouseLeave = function () {
		this.mouse.x = 0.5;
		this.mouse.y = 0.5;
	};

	LiquidBackgroundInstance.prototype.updateMouseListeners = function ( forceRemove ) {
		var shouldListen = ! forceRemove && this.config.mouse && this.config.mouseInfluence > 0 && ! this.isDisabledByEnvironment();
		if ( shouldListen && ! this.mouseListening ) {
			this.root.addEventListener( 'mousemove', this.boundMouseMove, { passive: true } );
			this.root.addEventListener( 'mouseleave', this.boundMouseLeave, { passive: true } );
			this.mouseListening = true;
		} else if ( ! shouldListen && this.mouseListening ) {
			this.root.removeEventListener( 'mousemove', this.boundMouseMove );
			this.root.removeEventListener( 'mouseleave', this.boundMouseLeave );
			this.mouseListening = false;
			this.handleMouseLeave();
		}
	};

	LiquidBackgroundInstance.prototype.resize = function () {
		var rect;
		var device;
		var dpr;
		var quality;
		var width;
		var height;

		if ( ! this.canvas || ! this.gl ) {
			return;
		}

		rect = this.root.getBoundingClientRect();
		if ( rect.width < 1 || rect.height < 1 ) {
			return;
		}

		device = currentDevice( this.config );
		dpr = Math.min( window.devicePixelRatio || 1, device === 'mobile' ? 1 : 1.5 );
		quality = device === 'mobile' ? 0.62 : ( device === 'tablet' ? 0.72 : 0.85 );
		width = Math.max( 1, Math.round( rect.width * dpr * quality ) );
		height = Math.max( 1, Math.round( rect.height * dpr * quality ) );

		if ( this.width !== width || this.height !== height ) {
			this.width = width;
			this.height = height;
			this.canvas.width = width;
			this.canvas.height = height;
			this.gl.viewport( 0, 0, width, height );
		}
		this.resizeQueued = false;
	};

	LiquidBackgroundInstance.prototype.render = function ( timestamp ) {
		var gl = this.gl;
		var primary;
		var secondary;
		var background;
		var time;
		var hasSecondary;

		if ( ! gl || this.lost || this.isDisabledByEnvironment() || ( ! this.visible && ! this.staticMode ) ) {
			return;
		}

		if ( this.resizeQueued ) {
			this.resize();
		}
		if ( ! this.width || ! this.height ) {
			return;
		}

		background = colorToVector( this.config.background, '#000000' );
		primary = colorToVector( this.config.primary, '#6C3BFF' );
		hasSecondary = this.config.secondary ? 1 : 0;
		secondary = colorToVector( this.config.secondary || this.config.primary, this.config.primary );
		time = ( this.staticMode ? 0.37 : timestamp * 0.001 ) * deviceNumber( this.config, 'speed' ) + this.config.seed * 0.017;

		gl.useProgram( this.program );
		gl.uniform2f( this.uniforms.u_resolution, this.width, this.height );
		gl.uniform2f( this.uniforms.u_mouse, this.mouse.x, this.mouse.y );
		gl.uniform3f( this.uniforms.u_background, background[ 0 ], background[ 1 ], background[ 2 ] );
		gl.uniform3f( this.uniforms.u_primary, primary[ 0 ], primary[ 1 ], primary[ 2 ] );
		gl.uniform3f( this.uniforms.u_secondary, secondary[ 0 ], secondary[ 1 ], secondary[ 2 ] );
		gl.uniform1f( this.uniforms.u_has_secondary, hasSecondary );
		gl.uniform1f( this.uniforms.u_time, time );
		gl.uniform1f( this.uniforms.u_scale, deviceNumber( this.config, 'scale' ) );
		gl.uniform1f( this.uniforms.u_complexity, this.config.complexity );
		gl.uniform1f( this.uniforms.u_distortion, this.config.distortion );
		gl.uniform1f( this.uniforms.u_softness, this.config.softness );
		gl.uniform1f( this.uniforms.u_contrast, this.config.contrast );
		gl.uniform1f( this.uniforms.u_opacity, deviceNumber( this.config, 'opacity' ) );
		gl.uniform1f( this.uniforms.u_mouse_influence, this.config.mouse ? this.config.mouseInfluence : 0 );
		gl.uniform1f( this.uniforms.u_direction, directionNumber( this.config.direction ) );
		gl.uniform1f( this.uniforms.u_seed, this.config.seed );
		gl.drawArrays( gl.TRIANGLES, 0, 3 );
	};

	LiquidBackgroundInstance.prototype.canAnimate = function () {
		return !! ( this.gl && ! this.staticMode && ! this.lost && this.visible && ! this.isDisabledByEnvironment() );
	};

	LiquidBackgroundInstance.prototype.handleEnvironmentChange = function () {
		this.resizeQueued = true;
		this.applyMode();
		if ( this.staticMode ) {
			this.render( 0 );
		}
	};

	LiquidBackgroundInstance.prototype.destroy = function () {
		if ( this.resizeObserver ) {
			this.resizeObserver.disconnect();
			this.resizeObserver = null;
		}
		if ( this.intersectionObserver ) {
			this.intersectionObserver.disconnect();
			this.intersectionObserver = null;
		}
		this.teardownVisual();
		instances.delete( this.root );
	};

	function hasAnimatedInstances() {
		var active = false;
		instances.forEach( function ( instance ) {
			if ( instance.canAnimate() ) {
				active = true;
			}
		} );
		return active;
	}

	function ensureLoop() {
		if ( document.visibilityState === 'hidden' || ! hasAnimatedInstances() ) {
			if ( renderFrame ) {
				window.cancelAnimationFrame( renderFrame );
				renderFrame = 0;
			}
			return;
		}

		if ( ! renderFrame ) {
			renderFrame = window.requestAnimationFrame( render );
		}
	}

	function render( timestamp ) {
		renderFrame = 0;
		if ( document.visibilityState === 'hidden' ) {
			return;
		}

		if ( timestamp - lastFrameTime >= targetFrameTime ) {
			lastFrameTime = timestamp;
			instances.forEach( function ( instance ) {
				instance.render( timestamp );
			} );
		}

		ensureLoop();
	}

	function sync() {
		syncFrame = 0;

		instances.forEach( function ( instance, root ) {
			if ( ! root.isConnected || ! root.hasAttribute( dataName ) ) {
				instance.destroy();
			}
		} );

		document.querySelectorAll( selector ).forEach( function ( root ) {
			var rawConfig = root.getAttribute( dataName );
			var config = readConfig( root );
			var instance;

			if ( ! config ) {
				return;
			}

			instance = instances.get( root );
			if ( instance ) {
				if ( rawConfig !== instance.rawConfig ) {
					instance.updateConfig( config, rawConfig );
				}
			} else {
				instances.set( root, new LiquidBackgroundInstance( root, config, rawConfig ) );
			}
		} );

		ensureLoop();
	}

	function scheduleSync() {
		if ( ! syncFrame ) {
			syncFrame = window.requestAnimationFrame( sync );
		}
	}

	function updateEnvironment() {
		instances.forEach( function ( instance ) {
			instance.handleEnvironmentChange();
		} );
		ensureLoop();
	}

	function applyEditorSettings( root, settings ) {
		var config;
		var id;

		if ( ! root || ! root.setAttribute || ! settings ) {
			return;
		}

		id = root.getAttribute( 'data-id' ) || root.id || '';
		config = configFromSettings( settings, id );
		if ( config ) {
			root.setAttribute( dataName, JSON.stringify( config ) );
		} else {
			root.removeAttribute( dataName );
		}
		scheduleSync();
	}

	function registerElementorHook() {
		var frontend = window.elementorFrontend;
		var modules = window.elementorModules;
		var Base;
		var Handler;

		if ( ! frontend || ! frontend.hooks ) {
			return;
		}

		if ( ! elementorHookRegistered ) {
			frontend.hooks.addAction( 'frontend/element_ready/global', function ( $scope ) {
				var root = $scope && $scope[ 0 ];
				if ( root && root.querySelectorAll ) {
					scheduleSync();
				}
			}, 1 );
			elementorHookRegistered = true;
		}

		if ( elementorHandlerRegistered || ! frontend.isEditMode || ! frontend.isEditMode() ) {
			return;
		}

		Base = modules && modules.frontend && modules.frontend.handlers && modules.frontend.handlers.Base;
		if ( ! Base || ! Base.extend || ! frontend.elementsHandler || ! frontend.elementsHandler.attachHandler ) {
			return;
		}

		Handler = Base.extend( {
			onInit: function () {
				Base.prototype.onInit.apply( this, arguments );
				this.refreshLiquidBackground();
			},
			onElementChange: function () {
				this.refreshLiquidBackground();
			},
			refreshLiquidBackground: function () {
				var root = this.$element && this.$element[ 0 ];
				if ( root && this.getElementSettings ) {
					applyEditorSettings( root, this.getElementSettings() );
				}
			}
		} );

		try {
			frontend.elementsHandler.attachHandler( 'container', Handler );
			elementorHandlerRegistered = true;
		} catch ( error ) {
			elementorHandlerRegistered = true;
		}
	}

	function init() {
		var observer;

		registerElementorHook();
		sync();

		window.addEventListener( 'resize', updateEnvironment, { passive: true } );
		window.addEventListener( 'orientationchange', updateEnvironment, { passive: true } );
		document.addEventListener( 'visibilitychange', ensureLoop );

		if ( reducedMotion.addEventListener ) {
			reducedMotion.addEventListener( 'change', updateEnvironment );
		} else if ( reducedMotion.addListener ) {
			reducedMotion.addListener( updateEnvironment );
		}

		observer = new MutationObserver( scheduleSync );
		observer.observe( document.body, {
			childList: true,
			subtree: true,
			attributes: true,
			attributeFilter: [ dataName ]
		} );
	}

	registerElementorHook();
	if ( ! elementorHookRegistered && window.jQuery ) {
		window.jQuery( window ).on( 'elementor/frontend/init', registerElementorHook );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init, { once: true } );
	} else {
		init();
	}
} )();
