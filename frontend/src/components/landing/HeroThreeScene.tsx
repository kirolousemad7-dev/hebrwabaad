import { useEffect, useRef } from 'react'
import * as THREE from 'three'

export default function HeroThreeScene() {
  const hostRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    const host = hostRef.current
    if (!host) {
      return
    }

    const width = host.clientWidth || 480
    const height = host.clientHeight || 420
    const scene = new THREE.Scene()
    const camera = new THREE.PerspectiveCamera(36, width / height, 0.1, 100)
    camera.position.z = 6.2

    const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true })
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 1.75))
    renderer.setSize(width, height)
    renderer.domElement.style.width = '100%'
    renderer.domElement.style.height = '100%'
    host.append(renderer.domElement)

    const gold = new THREE.Mesh(
      new THREE.TorusKnotGeometry(0.9, 0.28, 96, 16),
      new THREE.MeshStandardMaterial({ color: 0xc9a227, metalness: 0.72, roughness: 0.28 }),
    )
    gold.position.set(0.35, 0.15, 0)
    scene.add(gold)

    const navy = new THREE.Mesh(
      new THREE.IcosahedronGeometry(0.85, 0),
      new THREE.MeshStandardMaterial({ color: 0x12324d, metalness: 0.35, roughness: 0.4 }),
    )
    navy.position.set(-1.35, -0.55, -0.4)
    scene.add(navy)

    const accent = new THREE.Mesh(
      new THREE.OctahedronGeometry(0.42),
      new THREE.MeshStandardMaterial({ color: 0x2a9aa3, metalness: 0.45, roughness: 0.35 }),
    )
    accent.position.set(1.4, -0.9, 0.3)
    scene.add(accent)

    scene.add(new THREE.AmbientLight(0xffffff, 0.7))
    const key = new THREE.DirectionalLight(0xfff4d6, 1.4)
    key.position.set(3, 4, 6)
    scene.add(key)

    let frame = 0
    let pointerX = 0
    let pointerY = 0
    const onMove = (event: PointerEvent) => {
      const rect = host.getBoundingClientRect()
      pointerX = ((event.clientX - rect.left) / rect.width - 0.5) * 0.6
      pointerY = ((event.clientY - rect.top) / rect.height - 0.5) * 0.4
    }
    host.addEventListener('pointermove', onMove)

    const resize = () => {
      const nextWidth = host.clientWidth || width
      const nextHeight = host.clientHeight || height
      camera.aspect = nextWidth / nextHeight
      camera.updateProjectionMatrix()
      renderer.setSize(nextWidth, nextHeight)
    }
    const observer = new ResizeObserver(resize)
    observer.observe(host)

    const tick = () => {
      if (document.hidden) {
        frame = window.requestAnimationFrame(tick)
        return
      }
      gold.rotation.x += 0.004
      gold.rotation.y += 0.007
      navy.rotation.y -= 0.006
      accent.rotation.x += 0.01
      camera.position.x += (pointerX - camera.position.x) * 0.04
      camera.position.y += (-pointerY - camera.position.y) * 0.04
      camera.lookAt(0, 0, 0)
      renderer.render(scene, camera)
      frame = window.requestAnimationFrame(tick)
    }
    frame = window.requestAnimationFrame(tick)

    return () => {
      window.cancelAnimationFrame(frame)
      observer.disconnect()
      host.removeEventListener('pointermove', onMove)
      renderer.dispose()
      gold.geometry.dispose()
      navy.geometry.dispose()
      accent.geometry.dispose()
      ;(gold.material as THREE.Material).dispose()
      ;(navy.material as THREE.Material).dispose()
      ;(accent.material as THREE.Material).dispose()
      renderer.domElement.remove()
    }
  }, [])

  return <div ref={hostRef} className="absolute inset-0" />
}
