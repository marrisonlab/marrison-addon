( function () {
	'use strict';

	var selector = '[data-marrison-horizontal-scroll]';
	var instances = new Map();
	var reducedMotion = window.matchMedia( '(prefers-reduced-motion: reduce)' );
	var frame = 0;
	var syncFrame = 0;
	var activeCount = 0;
	var motionFXScroll = null;
	var nativeViewportPercentage = null;
	var bridgedViewportPercentage = null;
	var elementorHookRegistered = false;

	function clamp( value ) {
		return Math.max( 0, Math.min( 1, value ) );
	}

	function pinnedProgress( instance ) {
		return clamp( pinnedRawProgress( instance ) );
	}

	function pinnedRawProgress( instance ) {
		var scrollY = window.scrollY || window.pageYOffset;
		return ( scrollY - getSceneTop( instance ) ) / instance.verticalDistance;
	}

	function getSceneTop( instance ) {
		if ( typeof instance.sceneTop === 'number' ) {
			return instance.sceneTop;
		}

		return measureSceneTop( instance );
	}

	function measureSceneTop( instance ) {
		instance.sceneTop = instance.scene.getBoundingClientRect().top + ( window.scrollY || window.pageYOffset );
		return instance.sceneTop;
	}

	function snapProgressCount( instance ) {
		return instance.snapPositions ? instance.snapPositions.length : 0;
	}

	function snapIndexFromProgress( instance ) {
		var count = snapProgressCount( instance );
		if ( count < 2 ) {
			return 0;
		}

		return Math.max( 0, Math.min( count - 1, Math.round( pinnedProgress( instance ) * ( count - 1 ) ) ) );
	}

	function scrollToSnapIndex( instance, index ) {
		var count = snapProgressCount( instance );
		if ( count < 2 ) {
			return;
		}

		var boundedIndex = Math.max( 0, Math.min( count - 1, index ) );
		var progress = boundedIndex / ( count - 1 );
		var targetY = getSceneTop( instance ) + ( instance.verticalDistance * progress );
		instance.snapWheelLockedUntil = Date.now() + 420;

		if ( typeof window.scrollTo === 'function' ) {
			try {
				window.scrollTo( {
					top: targetY,
					behavior: 'smooth'
				} );
				return;
			} catch ( error ) {
				window.scrollTo( 0, targetY );
			}
		}
	}

	function snapWheelThreshold( instance ) {
		var threshold = Number( instance.config.snapThreshold || 220 );
		if ( ! isFinite( threshold ) ) {
			threshold = 220;
		}

		return Math.max( 40, Math.min( 600, threshold ) );
	}

	function wheelDelta( event ) {
		var primary = Math.abs( event.deltaY ) >= Math.abs( event.deltaX ) ? event.deltaY : event.deltaX;
		if ( event.deltaMode === 1 ) {
			primary *= 16;
		} else if ( event.deltaMode === 2 ) {
			primary *= window.innerHeight;
		}

		return primary === 0 ? 0 : primary;
	}

	function findSnapWheelInstance( event ) {
		var scrollY = window.scrollY || window.pageYOffset;
		var eventTarget = event.target;
		var found = null;

		instances.forEach( function ( instance ) {
			if ( found || instance.mode !== 'active' || ! instance.pinned || ! instance.config.snap || snapProgressCount( instance ) < 2 || instance.verticalDistance <= 0 ) {
				return;
			}

			var sceneTop = getSceneTop( instance );
			var pinnedStart = sceneTop - 1;
			var pinnedEnd = sceneTop + instance.verticalDistance + 1;
			if ( scrollY < pinnedStart || scrollY > pinnedEnd ) {
				return;
			}

			if ( eventTarget && instance.scene.contains( eventTarget ) ) {
				found = instance;
				return;
			}

			var viewportRect = instance.viewport.getBoundingClientRect();
			if ( viewportRect.top < window.innerHeight && viewportRect.bottom > 0 ) {
				found = instance;
			}
		} );

		return found;
	}

	function handleSnapWheel( event ) {
		if ( ! event.cancelable ) {
			return;
		}

		var instance = findSnapWheelInstance( event );
		if ( ! instance ) {
			return;
		}

		var delta = wheelDelta( event );
		if ( ! delta ) {
			return;
		}

		var now = Date.now();
		if ( instance.snapWheelLockedUntil && now < instance.snapWheelLockedUntil ) {
			event.preventDefault();
			return;
		}

		var direction = delta > 0 ? 1 : -1;
		var currentIndex = snapIndexFromProgress( instance );
		var nextIndex = currentIndex + direction;
		var count = snapProgressCount( instance );
		if ( nextIndex < 0 || nextIndex >= count ) {
			instance.snapWheelAccumulator = 0;
			instance.snapWheelDirection = 0;
			instance.snapWheelLastAt = 0;
			return;
		}

		event.preventDefault();
		if ( instance.snapWheelLastAt && now - instance.snapWheelLastAt > 350 ) {
			instance.snapWheelAccumulator = 0;
			instance.snapWheelDirection = 0;
		}
		if ( instance.snapWheelDirection !== direction ) {
			instance.snapWheelAccumulator = 0;
			instance.snapWheelDirection = direction;
		}
		instance.snapWheelLastAt = now;
		instance.snapWheelAccumulator += Math.abs( delta );
		if ( instance.snapWheelAccumulator < snapWheelThreshold( instance ) ) {
			return;
		}

		instance.snapWheelAccumulator = 0;
		scrollToSnapIndex( instance, nextIndex );
	}

	function findPinnedMotionFXInstance( element ) {
		if ( ! element || ! element.closest || ! element.classList || ! element.classList.contains( 'elementor-motion-effects-parent' ) ) {
			return null;
		}

		var scene = element.closest( '.marrison-horizontal-scroll-scene' );
		var found = null;
		if ( scene ) {
			instances.forEach( function ( instance ) {
				if ( instance.scene === scene && instance.mode === 'active' && instance.pinned && instance.verticalDistance > 0 ) {
					found = instance;
				}
			} );
		}
		return found;
	}

	function installMotionFXBridge() {
		if ( bridgedViewportPercentage ) {
			return;
		}

		var modules = window.elementorModules;
		var scroll = modules && modules.utils && modules.utils.Scroll;
		if ( ! scroll || typeof scroll.getElementViewportPercentage !== 'function' ) {
			return;
		}

		motionFXScroll = scroll;
		nativeViewportPercentage = scroll.getElementViewportPercentage;
		var original = nativeViewportPercentage;
		bridgedViewportPercentage = function ( $element ) {
			var percentage = original.apply( this, arguments );
			// Pro Elements calls this with one argument for viewport-based Motion Effects.
			if ( arguments.length !== 1 || ! $element || ! $element[ 0 ] ) {
				return percentage;
			}

			var instance = findPinnedMotionFXInstance( $element[ 0 ] );
			if ( ! instance ) {
				return percentage;
			}

			// Start at Elementor's current value, then finish its range during the pin.
			return Math.round( ( percentage + ( 100 - percentage ) * pinnedProgress( instance ) ) * 100 ) / 100;
		};
		scroll.getElementViewportPercentage = bridgedViewportPercentage;
	}

	function removeMotionFXBridge() {
		if ( motionFXScroll && motionFXScroll.getElementViewportPercentage === bridgedViewportPercentage ) {
			motionFXScroll.getElementViewportPercentage = nativeViewportPercentage;
			motionFXScroll = null;
			nativeViewportPercentage = null;
			bridgedViewportPercentage = null;
		}
	}

	function readConfig( root ) {
		try {
			var config = JSON.parse( root.getAttribute( 'data-marrison-horizontal-scroll' ) || '' );
			return config && config.devices && config.breakpoints ? config : null;
		} catch ( error ) {
			return null;
		}
	}

	function currentDevice( config ) {
		var width = window.innerWidth;
		if ( width <= Number( config.breakpoints.mobile || 767 ) ) {
			return 'mobile';
		}
		if ( width <= Number( config.breakpoints.tablet || 1024 ) ) {
			return 'tablet';
		}
		return 'desktop';
	}

	function shouldRun( config ) {
		return ! reducedMotion.matches && !! config.devices[ currentDevice( config ) ];
	}

	function hasBackgroundScale( instance ) {
		return !! (
			instance.config.backgroundScale &&
			instance.config.backgroundScale.enabled
		);
	}

	function rememberInlineStyle( element, property ) {
		return {
			value: element.style.getPropertyValue( property ),
			priority: element.style.getPropertyPriority( property )
		};
	}

	function restoreInlineStyle( element, property, snapshot ) {
		if ( ! snapshot ) {
			return;
		}
		if ( snapshot.value ) {
			element.style.setProperty( property, snapshot.value, snapshot.priority );
		} else {
			element.style.removeProperty( property );
		}
	}

	function restoreFollowingShift( instance ) {
		if ( ! instance.followingShiftTargets ) {
			return;
		}

		instance.followingShiftTargets.forEach( function ( snapshot, element ) {
			restoreInlineStyle( element, 'translate', snapshot );
			element.classList.remove( 'marrison-horizontal-scroll-follow-shift' );
		} );
		instance.followingShiftTargets.clear();
	}

	function isShiftableFollower( element ) {
		if ( ! element || element.nodeType !== 1 ) {
			return false;
		}

		if ( /^(SCRIPT|STYLE|LINK|TEMPLATE|NOSCRIPT)$/.test( element.tagName ) ) {
			return false;
		}

		var style = window.getComputedStyle( element );
		if ( style.display === 'none' ) {
			return false;
		}

		return ! /^(fixed|sticky|absolute)$/.test( style.position );
	}

	function getNextElementSibling( element ) {
		if ( element.nextElementSibling ) {
			return element.nextElementSibling;
		}
		if ( ! element.parentNode || ! element.parentNode.children ) {
			return null;
		}

		var siblings = element.parentNode.children;
		var index = Array.prototype.indexOf.call( siblings, element );
		if ( index < 0 ) {
			return null;
		}

		for ( var i = index + 1; i < siblings.length; i++ ) {
			if ( siblings[ i ] && siblings[ i ].nodeType !== 3 ) {
				return siblings[ i ];
			}
		}

		return null;
	}

	function getFollowingShiftTargets( instance ) {
		if ( ! instance.scene ) {
			return [];
		}

		var targets = [];
		var added = new Set();
		var level = instance.scene;
		var direct = true;

		while ( level && level !== document.body ) {
			for ( var sibling = getNextElementSibling( level ); sibling; sibling = getNextElementSibling( sibling ) ) {
				if ( added.has( sibling ) ) {
					continue;
				}
				if ( direct || isShiftableFollower( sibling ) ) {
					targets.push( sibling );
					added.add( sibling );
				}
			}
			level = level.parentElement;
			direct = false;
		}

		return targets;
	}

	function updateFollowingShift( instance, progress ) {
		if ( ! instance.revealFollowing || progress >= 1 ) {
			restoreFollowingShift( instance );
			return;
		}

		var shiftedProgress = clamp( progress );
		// Keep a tiny overlap to hide subpixel seams between the pinned section and the following content.
		var offset = Math.round( ( -instance.verticalDistance * ( 1 - shiftedProgress ) - 1 ) * 100 ) / 100;
		if ( Math.abs( offset ) < 0.5 ) {
			restoreFollowingShift( instance );
			return;
		}

		var activeTargets = instance.followingTargets || getFollowingShiftTargets( instance );
		var activeSet = new Set( activeTargets );
		instance.followingShiftTargets.forEach( function ( snapshot, element ) {
			if ( ! activeSet.has( element ) ) {
				restoreInlineStyle( element, 'translate', snapshot );
				element.classList.remove( 'marrison-horizontal-scroll-follow-shift' );
				instance.followingShiftTargets.delete( element );
			}
		} );

		activeTargets.forEach( function ( element ) {
			if ( ! instance.followingShiftTargets.has( element ) ) {
				instance.followingShiftTargets.set( element, rememberInlineStyle( element, 'translate' ) );
			}
			element.classList.add( 'marrison-horizontal-scroll-follow-shift' );
			element.style.setProperty( 'translate', '0 ' + offset + 'px' );
		} );
	}

	function isVisibleBackgroundColor( value ) {
		if ( ! value || value === 'transparent' ) {
			return false;
		}
		return ! /^rgba?\(\s*0\s*,\s*0\s*,\s*0\s*(?:,\s*0\s*)?\)$/i.test( value );
	}

	function findOwnBackgroundVideo( root, host ) {
		var video = Array.prototype.filter.call( root.children, function ( child ) {
			return child.classList.contains( 'elementor-background-video-container' );
		} )[ 0 ] || null;

		if ( video || ! host || host === root ) {
			return video;
		}

		return Array.prototype.filter.call( host.children, function ( child ) {
			return child.classList.contains( 'elementor-background-video-container' );
		} )[ 0 ] || null;
	}

	function setupBackgroundScale( instance ) {
		if ( ! hasBackgroundScale( instance ) || instance.backgroundLayer ) {
			return;
		}

		var video = findOwnBackgroundVideo( instance.root, instance.host );
		if ( video ) {
			instance.backgroundLayer = video;
			instance.backgroundLayerIsGenerated = false;
			instance.backgroundTransformStyle = rememberInlineStyle( video, 'transform' );
			video.classList.add( 'marrison-horizontal-scroll-bg-scale-video' );
			updateBackgroundScale( instance, 0 );
			return;
		}

		var style = window.getComputedStyle( instance.root );
		var hasImage = style.backgroundImage && style.backgroundImage !== 'none';
		var hasColor = isVisibleBackgroundColor( style.backgroundColor );
		if ( ! hasImage && ! hasColor ) {
			return;
		}

		var layer = document.createElement( 'div' );
		layer.className = 'marrison-horizontal-scroll-bg-scale';
		if ( hasImage ) {
			layer.style.backgroundImage = style.backgroundImage;
			layer.style.backgroundPosition = style.backgroundPosition;
			layer.style.backgroundSize = style.backgroundSize;
			layer.style.backgroundRepeat = style.backgroundRepeat;
			layer.style.backgroundOrigin = style.backgroundOrigin;
			layer.style.backgroundClip = style.backgroundClip;
			layer.style.backgroundBlendMode = style.backgroundBlendMode;
		} else {
			layer.style.backgroundColor = style.backgroundColor;
		}

		instance.backgroundLayer = layer;
		instance.backgroundLayerIsGenerated = true;
		instance.backgroundImageStyle = rememberInlineStyle( instance.root, 'background-image' );
		instance.backgroundColorStyle = rememberInlineStyle( instance.root, 'background-color' );
		instance.positionStyle = rememberInlineStyle( instance.root, 'position' );
		if ( style.position === 'static' ) {
			instance.root.style.setProperty( 'position', 'relative' );
		}
		if ( hasImage ) {
			instance.root.style.setProperty( 'background-image', 'none' );
		} else {
			instance.root.style.setProperty( 'background-color', 'transparent' );
		}
		instance.root.insertBefore( layer, instance.root.firstChild );
		updateBackgroundScale( instance, 0 );
	}

	function updateBackgroundScale( instance, progress ) {
		if ( ! instance.backgroundLayer ) {
			return;
		}

		var settings = instance.config.backgroundScale;
		var from = Number( settings.from );
		var to = Number( settings.to );
		var scale = ( isFinite( from ) ? from : 0 ) + ( ( isFinite( to ) ? to : 1 ) - ( isFinite( from ) ? from : 0 ) ) * progress;
		var roundedScale = Math.round( scale * 10000 ) / 10000;
		if ( roundedScale !== instance.lastBackgroundScale ) {
			instance.backgroundLayer.style.transform = 'scale(' + roundedScale + ')';
			instance.lastBackgroundScale = roundedScale;
		}
	}

	function teardownBackgroundScale( instance ) {
		if ( instance.backgroundLayer ) {
			if ( instance.backgroundLayerIsGenerated ) {
				instance.backgroundLayer.remove();
			} else {
				instance.backgroundLayer.classList.remove( 'marrison-horizontal-scroll-bg-scale-video' );
				restoreInlineStyle( instance.backgroundLayer, 'transform', instance.backgroundTransformStyle );
			}
			instance.backgroundLayer = null;
		}
		restoreInlineStyle( instance.root, 'background-image', instance.backgroundImageStyle );
		restoreInlineStyle( instance.root, 'background-color', instance.backgroundColorStyle );
		restoreInlineStyle( instance.root, 'position', instance.positionStyle );
		instance.backgroundImageStyle = null;
		instance.backgroundColorStyle = null;
		instance.backgroundLayerIsGenerated = null;
		instance.backgroundTransformStyle = null;
		instance.positionStyle = null;
		instance.lastBackgroundScale = null;
	}

	function getContentHost( root ) {
		for ( var i = 0; i < root.children.length; i++ ) {
			if ( root.children[ i ].classList.contains( 'e-con-inner' ) ) {
				return root.children[ i ];
			}
		}
		return root;
	}

	function getContentChildren( host ) {
		return Array.prototype.filter.call( host.children, function ( child ) {
			return child.classList.contains( 'elementor-element' );
		} );
	}

	function hasStickyBlockingAncestor( scene ) {
		for ( var parent = scene.parentElement; parent && parent !== document.body; parent = parent.parentElement ) {
			var style = window.getComputedStyle( parent );
			if ( /^(auto|scroll|hidden|clip)$/.test( style.overflowY ) ) {
				return true;
			}
		}
		return false;
	}

	function requestPaint() {
		if ( activeCount && ! frame ) {
			frame = window.requestAnimationFrame( paintAll );
		}
	}

	function paintAll() {
		frame = 0;
		instances.forEach( function ( instance ) {
			if ( instance.mode === 'active' ) {
				paint( instance );
			}
		} );
	}

	function paint( instance ) {
		var progress;

		if ( instance.pinned ) {
			var rawProgress = pinnedRawProgress( instance );
			progress = clamp( rawProgress );
			updateFollowingShift( instance, rawProgress );
		} else {
			restoreFollowingShift( instance );
			// Without pin the passage through the viewport supplies the scroll range.
			var scrollY = window.scrollY || window.pageYOffset;
			var sceneTop = getSceneTop( instance );
			var naturalRange = instance.scene.offsetHeight + window.innerHeight;
			var range = Math.max( 1, naturalRange * instance.config.speed );
			var center = sceneTop + ( instance.scene.offsetHeight - window.innerHeight ) / 2;
			progress = clamp( ( scrollY - ( center - range / 2 ) ) / range );
		}

		// Both directions cover the entire real overflow, with opposite endpoints.
		var offset = instance.overflow * ( instance.config.direction === 'ltr' ? 1 - progress : progress );
		if ( instance.config.snap && instance.snapPositions && instance.snapPositions.length > 1 ) {
			offset = instance.snapPositions[ Math.round( ( instance.snapPositions.length - 1 ) * ( instance.config.direction === 'ltr' ? 1 - progress : progress ) ) ];
		}
		var x = -offset;
		var roundedX = Math.round( x * 100 ) / 100;
		if ( roundedX !== instance.lastX ) {
			instance.mover.style.transform = 'translate3d(' + roundedX + 'px, 0, 0)';
			instance.lastX = roundedX;
		}
		if ( instance.config.snap && ! instance.snapReady ) {
			instance.snapReady = true;
			window.requestAnimationFrame( function () {
				if ( instance.mode === 'active' && instance.config.snap && instance.scene ) {
					instance.scene.classList.add( 'marrison-horizontal-scroll-snap-ready' );
				}
			} );
		}
		updateBackgroundScale( instance, progress );
	}

	function scheduleMeasure( instance ) {
		if ( instance.mode !== 'active' || instance.measureFrame ) {
			return;
		}
		instance.measureFrame = window.requestAnimationFrame( function () {
			instance.measureFrame = 0;
			if ( instance.mode === 'active' ) {
				measure( instance );
			}
		} );
	}

	function observeContentSizes( instance ) {
		if ( ! instance.resizeObserver ) {
			return;
		}

		instance.resizeTargets.forEach( function ( target ) {
			if ( ! instance.mover.contains( target ) ) {
				instance.resizeObserver.unobserve( target );
				instance.resizeTargets.delete( target );
			}
		} );

		function observe( element ) {
			if ( element.classList.contains( 'elementor-element' ) && ! instance.resizeTargets.has( element ) ) {
				instance.resizeObserver.observe( element );
				instance.resizeTargets.add( element );
			}
		}

		Array.prototype.forEach.call( instance.mover.children, function ( track ) {
			observe( track );
			Array.prototype.forEach.call( track.children, function ( child ) {
				observe( child );
				if ( child.classList.contains( 'e-con-inner' ) ) {
					Array.prototype.forEach.call( child.children, observe );
				}
			} );
		} );
	}

	function uniqueSnapPositions( positions ) {
		return positions.filter( function ( position, index ) {
			return index === 0 || Math.abs( position - positions[ index - 1 ] ) > 1;
		} );
	}

	function buildSnapPositions( instance ) {
		var positions = [ 0 ];
		Array.prototype.forEach.call( instance.mover.children, function ( child ) {
			var offset = Math.max( 0, Math.min( instance.overflow, child.offsetLeft - instance.mover.offsetLeft ) );
			positions.push( Math.round( offset * 100 ) / 100 );
		} );
		positions.push( Math.round( instance.overflow * 100 ) / 100 );
		positions.sort( function ( a, b ) {
			return a - b;
		} );
		return uniqueSnapPositions( positions );
	}

	function measure( instance ) {
		var hostStyle = window.getComputedStyle( instance.host );
		var hostWidth = Math.max( 0, instance.host.clientWidth -
			( parseFloat( hostStyle.paddingLeft ) || 0 ) -
			( parseFloat( hostStyle.paddingRight ) || 0 ) );
		var trackWidth = Math.max( instance.mover.scrollWidth, instance.mover.getBoundingClientRect().width );
		instance.overflow = Math.max( 0, trackWidth - hostWidth );
		instance.snapPositions = instance.config.snap && instance.overflow > 1 ? buildSnapPositions( instance ) : null;
		instance.verticalDistance = instance.overflow * instance.config.speed;
		measureSceneTop( instance );
		instance.pinned = !! instance.config.pin && instance.overflow > 1 &&
			instance.viewport.offsetHeight <= window.innerHeight + 1 &&
			! hasStickyBlockingAncestor( instance.scene );
		instance.revealFollowing = instance.pinned && instance.viewport.offsetHeight < window.innerHeight - 1;
		if ( instance.revealFollowing ) {
			instance.followingTargets = getFollowingShiftTargets( instance );
		} else {
			instance.followingTargets = null;
			restoreFollowingShift( instance );
		}

		instance.scene.classList.toggle( 'marrison-horizontal-scroll-pinned', instance.pinned );
		instance.scene.style.height = instance.pinned
			? ( instance.viewport.offsetHeight + instance.verticalDistance ) + 'px'
			: '';
		requestPaint();
	}

	function start( instance ) {
		var root = instance.root;
		var host = getContentHost( root );
		var children = getContentChildren( host );
		if ( ! children.length || ! root.parentNode ) {
			return;
		}

		// Reuse the same nodes after a breakpoint change: Pro keeps their parent references.
		var scene = instance.scene || document.createElement( 'div' );
		var viewport = instance.viewport || document.createElement( 'div' );
		var mover = instance.mover || document.createElement( 'div' );
		scene.classList.add( 'marrison-horizontal-scroll-scene' );
		viewport.classList.add( 'marrison-horizontal-scroll-viewport' );
		mover.classList.add( 'marrison-horizontal-scroll-mover' );
		scene.classList.toggle( 'marrison-horizontal-scroll-snap', !! instance.config.snap );
		scene.classList.remove( 'marrison-horizontal-scroll-snap-ready' );

		host.insertBefore( mover, children[ 0 ] );
		children.forEach( function ( child ) {
			mover.appendChild( child );
		} );
		root.parentNode.insertBefore( scene, root );
		scene.appendChild( viewport );
		viewport.appendChild( root );

		instance.mode = 'active';
		if ( ! activeCount ) {
			window.addEventListener( 'scroll', requestPaint, { passive: true } );
			window.addEventListener( 'wheel', handleSnapWheel, { passive: false } );
		}
		activeCount++;
		instance.host = host;
		instance.scene = scene;
		instance.viewport = viewport;
		instance.mover = mover;
		host.classList.toggle( 'marrison-horizontal-scroll-snap-host', !! instance.config.snap );
		setupBackgroundScale( instance );
		instance.overflow = 0;
		instance.snapPositions = null;
		instance.snapReady = false;
		instance.snapWheelAccumulator = 0;
		instance.snapWheelDirection = 0;
		instance.snapWheelLastAt = 0;
		instance.snapWheelLockedUntil = 0;
		instance.revealFollowing = false;
		instance.followingTargets = null;
		instance.followingShiftTargets = new Map();
		instance.sceneTop = null;
		instance.verticalDistance = 0;
		instance.lastX = null;
		installMotionFXBridge();
		instance.onContentLoad = function () {
			scheduleMeasure( instance );
		};
		root.addEventListener( 'load', instance.onContentLoad, true );

		if ( window.ResizeObserver ) {
			instance.resizeTargets = new Set();
			instance.resizeObserver = new ResizeObserver( function () {
				scheduleMeasure( instance );
			} );
			instance.resizeObserver.observe( root );
			instance.resizeObserver.observe( host );
			instance.resizeObserver.observe( mover );
			observeContentSizes( instance );
		}

		instance.contentObserver = new MutationObserver( function () {
			observeContentSizes( instance );
			scheduleMeasure( instance );
		} );
		instance.contentObserver.observe( mover, { childList: true, subtree: true, characterData: true } );

		if ( document.fonts && document.fonts.ready ) {
			document.fonts.ready.then( function () {
				scheduleMeasure( instance );
			} );
		}

		measure( instance );
	}

	function stop( instance ) {
		if ( instance.mode !== 'active' ) {
			return;
		}
		instance.mode = 'idle';
		activeCount--;
		if ( ! activeCount ) {
			window.removeEventListener( 'scroll', requestPaint );
			window.removeEventListener( 'wheel', handleSnapWheel );
			removeMotionFXBridge();
			if ( frame ) {
				window.cancelAnimationFrame( frame );
				frame = 0;
			}
		}
		if ( instance.measureFrame ) {
			window.cancelAnimationFrame( instance.measureFrame );
			instance.measureFrame = 0;
		}
		if ( instance.resizeObserver ) {
			instance.resizeObserver.disconnect();
			instance.resizeObserver = null;
			instance.resizeTargets.clear();
			instance.resizeTargets = null;
		}
		instance.contentObserver.disconnect();
		instance.root.removeEventListener( 'load', instance.onContentLoad, true );
		restoreFollowingShift( instance );
		instance.mover.style.removeProperty( 'transform' );
		instance.scene.classList.remove( 'marrison-horizontal-scroll-pinned' );
		instance.scene.classList.remove( 'marrison-horizontal-scroll-snap' );
		instance.scene.classList.remove( 'marrison-horizontal-scroll-snap-ready' );
		instance.scene.style.removeProperty( 'height' );
		instance.host.classList.remove( 'marrison-horizontal-scroll-snap-host' );
		teardownBackgroundScale( instance );

		if ( instance.mover.parentNode ) {
			while ( instance.mover.firstChild ) {
				instance.mover.parentNode.insertBefore( instance.mover.firstChild, instance.mover );
			}
			instance.mover.remove();
		}
		if ( instance.scene.parentNode ) {
			instance.scene.parentNode.insertBefore( instance.root, instance.scene );
			instance.scene.remove();
		}

		instance.host = null;
		instance.snapPositions = null;
		instance.snapReady = false;
		instance.snapWheelAccumulator = 0;
		instance.snapWheelDirection = 0;
		instance.snapWheelLastAt = 0;
		instance.snapWheelLockedUntil = 0;
		instance.revealFollowing = false;
		instance.followingTargets = null;
		instance.sceneTop = null;
	}

	function enterReduced( instance ) {
		if ( instance.mode === 'reduced' ) {
			return;
		}
		stop( instance );
		instance.mode = 'reduced';
		instance.originalOverflowX = instance.root.style.getPropertyValue( 'overflow-x' );
		instance.originalOverflowPriority = instance.root.style.getPropertyPriority( 'overflow-x' );
		instance.hadTabindex = instance.root.hasAttribute( 'tabindex' );
		instance.root.style.setProperty( 'overflow-x', 'auto' );
		if ( ! instance.hadTabindex ) {
			instance.root.setAttribute( 'tabindex', '0' );
		}
	}

	function leaveReduced( instance ) {
		if ( instance.mode !== 'reduced' ) {
			return;
		}
		if ( instance.originalOverflowX ) {
			instance.root.style.setProperty( 'overflow-x', instance.originalOverflowX, instance.originalOverflowPriority );
		} else {
			instance.root.style.removeProperty( 'overflow-x' );
		}
		if ( ! instance.hadTabindex ) {
			instance.root.removeAttribute( 'tabindex' );
		}
		instance.mode = 'idle';
	}

	function destroy( instance ) {
		stop( instance );
		leaveReduced( instance );
		instances.delete( instance.root );
		instance.scene = null;
		instance.viewport = null;
		instance.mover = null;
	}

	function sync() {
		syncFrame = 0;
		instances.forEach( function ( instance, root ) {
			if ( ! root.isConnected || root.getAttribute( 'data-marrison-horizontal-scroll' ) !== instance.rawConfig ) {
				destroy( instance );
			}
		} );

		document.querySelectorAll( selector ).forEach( function ( root ) {
			var instance = instances.get( root );
			if ( ! instance ) {
				var config = readConfig( root );
				if ( ! config ) {
					return;
				}
				instance = {
					root: root,
					config: config,
					rawConfig: root.getAttribute( 'data-marrison-horizontal-scroll' ),
					mode: 'idle',
					measureFrame: 0
				};
				instances.set( root, instance );
			}

			if ( reducedMotion.matches && instance.config.devices[ currentDevice( instance.config ) ] ) {
				enterReduced( instance );
			} else {
				leaveReduced( instance );
				if ( shouldRun( instance.config ) ) {
					if ( instance.mode !== 'active' ) {
						start( instance );
					} else {
						scheduleMeasure( instance );
					}
				} else {
					stop( instance );
				}
			}
		} );
		if ( activeCount ) {
			installMotionFXBridge();
		}
		requestPaint();
	}

	function scheduleSync() {
		if ( ! syncFrame ) {
			syncFrame = window.requestAnimationFrame( sync );
		}
	}

	function init() {
		registerElementorHook();
		sync();
		window.addEventListener( 'resize', scheduleSync, { passive: true } );
		window.addEventListener( 'orientationchange', scheduleSync, { passive: true } );
		if ( reducedMotion.addEventListener ) {
			reducedMotion.addEventListener( 'change', scheduleSync );
		} else if ( reducedMotion.addListener ) {
			reducedMotion.addListener( scheduleSync );
		}

		// Enabled only on a page that rendered the module. Handles dynamic Elementor content.
		var pageObserver = new MutationObserver( scheduleSync );
		pageObserver.observe( document.body, {
			childList: true,
			subtree: true,
			attributes: true,
			attributeFilter: [ 'data-marrison-horizontal-scroll' ]
		} );
	}

	function registerElementorHook() {
		var frontend = window.elementorFrontend;
		if ( elementorHookRegistered || ! frontend || ! frontend.hooks ) {
			return;
		}

		frontend.hooks.addAction( 'frontend/element_ready/global', function ( $scope ) {
			var root = $scope && $scope[ 0 ];
			if ( root && root.matches && root.matches( selector ) ) {
				// Run before Pro's default-priority handler captures the Motion Effects parent.
				sync();
			}
		}, 1 );
		elementorHookRegistered = true;
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
