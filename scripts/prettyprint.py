import lxml.etree as etree

import io, os


from icecream import ic

from datetime import datetime

def time_format():
    return f'{datetime.now()}|> '

ic.configureOutput(prefix=time_format)

WRITE_IF_ONLY_ATTRIBS_EXIST : bool = False

sourcePath = os.path.abspath("./_data/tmp/")
destPath   =  os.path.abspath(os.path.join(sourcePath, os.pardir))

ic (sourcePath)
ic (destPath)

def writeXML (theXML, fileName : str, thePart : str, level : int):
    # ic (type(theXML))
    parts = os.path.splitext(fileName)
    fileName = f"{parts[0]}" #".{level}"
    if (thePart is not None and len(thePart) > 0):
        fileName  += f".{thePart}"
    fileName += parts[1]
    ic (level,fileName)
    with io.open (fileName,"wb") as f:
        f.write ((etree.tostring(theXML, pretty_print=True)))
    return fileName


def doThisXML (root, fileName : str, level : int):

    if level > 20:
        raise Exception ("Are we stuck in a recursive loop????")

    tagCount = {}
    theChild = {}

    for child in root:
        if child.tag in tagCount:
            tagCount[child.tag] += 1
            # theChild[child.tag] = None
        else: #  we're only going to write the children if there's only a single instance
            tagCount[child.tag]  = 1
            theChild[child.tag]  = child

    for k in [k for k in tagCount
                if  (   True # tagCount[k]          == 1           # this is not a collection
                 and    (   len(theChild[k]) > 0            # has child element(s)
                        or  (len(theChild[k].attrib) > 0    # has attribute(s)
                            and WRITE_IF_ONLY_ATTRIBS_EXIST
                            )
                         )
                    )
             ]:
        childName = writeXML(theChild[k],fileName,k,(level+1))
        doThisXML (theChild[k],childName,(level+1)) # Get all recursive about it...


def doOne (fileName : str):
    source = os.path.join (sourcePath,fileName)
    dest   = os.path.join (destPath,fileName)

    theXML = etree.parse(source)
    
    thisName =writeXML(theXML,dest,None,0)
    root = theXML.getroot()
    doThisXML (root, thisName,0)


for file in [file for file in os.scandir(sourcePath) if os.path.isfile(file)]:
    doOne (fileName = os.path.basename(file))