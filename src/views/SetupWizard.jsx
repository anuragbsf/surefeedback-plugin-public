import React, { useEffect } from 'react'

const SetupWizard = () => {
  useEffect(() => {
    const body = document.body
    body.classList.add('surefeedback-onboarding-fullscreen')

    return () => {
      body.classList.remove('surefeedback-onboarding-fullscreen')
    }
  }, [])

  return (
    <div>SetupWizard</div>
  )
}

export default SetupWizard