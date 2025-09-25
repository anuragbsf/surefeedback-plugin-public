import React from 'react'
import NavMenu from '../components/NavMenu'
import { Container } from "@bsf/force-ui";
import ConnectionCard  from './ConnectionCard.jsx'
import Connected from './Connected.jsx';
import UnverifiedState from './UnverifiedState.jsx';

const Connections = () => {
  // Get verification status from localized data
  const verificationStatus = window.sureFeedbackAdmin?.verification_status || 'unverified';
  const isVerified = verificationStatus === 'verified';

  return (
     <div id="surefeedback-dashboard-app" className="surefeedback-styles">
      <NavMenu />
      <div>
        <Container
                    align="stretch"
                    className="p-6 flex-col lg:flex-row box-border"
                    containerType="flex"
                    direction="row"
                    gap="sm"
                    justify="start"
                    style={{
                        width: "100%",
                    }}
                >
                    <Container.Item
                        className="p-2 w-full"
                        shrink={1}
                    >
                      {isVerified ? (
                        <Connected/>
                      ) : (
                        <UnverifiedState/>
                      )}
                    </Container.Item>
                </Container>
      </div>
    </div>
  )
}

export default Connections
