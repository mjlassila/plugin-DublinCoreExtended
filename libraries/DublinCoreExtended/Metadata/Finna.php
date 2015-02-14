<?php
/**
 * @package DublinCoreExtended
 * @subpackage MetadataFormats
 * @copyright Copyright 2009-2014 John Flatness, Yu-Hsun Lin
 * @copyright Copyright 2014 Daniel Berthereau
 * @copyright Copyright 2015 Matti Lassila
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

/**
 * Class implementing DCMI Metadata Terms metadata output (qdc).
 *
 * This format is not standardized, but used by some repositories, as DSpace
 * and a mediawiki extension (ProofreadPage: https://wikisource.org/wiki/Special:ProofreadIndexOaiSchema/qdc).
 * The schema comes from the Science & Technology Facilities Council of the
 * United Kingdom.
 *
 * @see http://epubs.cclrc.ac.uk/xsd/qdc.xsd
 * @see http://dublincore.org/schemas/xmls/qdc/dcterms.xsd
 *
 * @see OaiPmhRepository_Metadata_FormatInterface
 * @package DublinCoreExtended
 * @subpackage Metadata Formats
 */
class DublinCoreExtended_Metadata_Finna implements OaiPmhRepository_Metadata_FormatInterface
{
    /** OAI-PMH metadata prefix */
    const METADATA_PREFIX = 'qdc_finna';

    /** XML namespace for output format */
    const METADATA_NAMESPACE = 'http://epubs.cclrc.ac.uk/xmlns/qdc/';

    /** XML schema for output format */
    const METADATA_SCHEMA = 'http://epubs.cclrc.ac.uk/xsd/qdc.xsd';

    /** XML namespace for qualified Dublin Core */
    const QDC_NAMESPACE_URI = 'http://epubs.cclrc.ac.uk/xmlns/qdc/';

    /** XML namespace for unqualified Dublin Core */
    const DC_NAMESPACE_URI = 'http://purl.org/dc/elements/1.1/';

    /** XML namepace for DC element refinements*/
    const DC_TERMS_NAMESPACE_URI = 'http://purl.org/dc/terms/';

    /** XML namepace for National Library of Finland OAI namespace*/
    const DC_KK_NAMESPACE_URI = 'http://purl.org/dc/terms/';

    /**
     * Appends Dublin Core metadata.
     *
     * Appends a metadata element, a child element with the required format,
     * and further children for each of the Dublin Core fields present in the
     * item.
     */
    public function appendMetadata($item, $metadataElement)
    {
        $document = $metadataElement->ownerDocument;
        $qdc = $document->createElementNS(
            self::METADATA_NAMESPACE, 'dcterms:qualifieddc');
        $metadataElement->appendChild($qdc);

        
        $qdc->setAttribute('xmlns:dc', self::DC_NAMESPACE_URI);
        $qdc->setAttribute('xmlns:dcterms', self::DC_TERMS_NAMESPACE_URI);
        $qdc->setAttribute('xmlns:kk', self::DC_KK_NAMESPACE_URI);
        $qdc->declareSchemaLocation(self::METADATA_NAMESPACE, self::METADATA_SCHEMA);

        // Each of the 14 unqualified Dublin Core elements. Type is omitted
        // and handled separately
        $dcElementNames = array(
            'title', 'creator', 'subject', 'description',
            'publisher', 'contributor', 'date',
            'format', 'identifier', 'source', 'language',
            'relation', 'coverage', 'rights',
        );

        // Each of metadata terms.
        require dirname(dirname(dirname(dirname(__FILE__)))) . DIRECTORY_SEPARATOR . 'elements.php';
        $dcTermElements = &$elements;

        // Must create elements using createElement to make DOM allow a
        // top-level xmlns declaration instead of wasteful and non-compliant
        // per-node declarations.
        foreach ($dcTermElements as $element) {
            $elementName = $element['name'];
            $namespace = in_array($elementName, $dcElementNames)
                ? 'dc:'
                : 'dcterms:';

            $dcElements = $item->getElementTexts(
                'Dublin Core', $element['label']);

            foreach ($dcElements as $elementText) {
                // This check avoids some issues with useless data.
                $value = trim($elementText->text);
                if ($value || $value === '0') {
                    if ($elementName == 'identifier') {
                        if (substr($value, 0, 3) == 'URN') {
                            $uri = $qdc->appendNewElement('dc:identifier', trim($value), 'URI');
                            $uri->setAttribute('type', 'uri');

                        } 
                    }

                    else if ($elementName == "subject") {
                        if (is_numeric(substr(trim($value), 0, 1)) 
                            & !preg_match('/[A-Za-z]/', $value)
                            & !preg_match('/^18/', $value) 
                            & !preg_match('/^19/', $value)){
                        $subject = $qdc->appendNewElement('dc:subject',trim($value));
                        $subject->setAttribute('scheme','YKL');
                        } 
                        else {
                            $qdc->appendNewElement($namespace . $elementName, $value);
                        }
                    }
                    else if ($elementName == "language") {
                        $qdc->appendNewElement($namespace . $elementName, $this->standardizeLanguage($value));
                    }
                    else if ($elementName == "type") {
                        $qdc->appendNewElement($namespace . $elementName, $this->translateItemType($value));
                    }  
                    else {
                        $qdc->appendNewElement($namespace . $elementName, $value);
                    }
                }
            }   

            // Append the browse URI to all results.
            if ($elementName == 'identifier') {
                
                $coolUri = $qdc->appendNewElement('dc:identifier', record_url($item, 'show', true));
                $coolUri->setAttribute('type', 'cooluri');
                // Also append an identifier for each file.
                if (get_option('oaipmh_repository_expose_files') && metadata($item, 'has files')) {
                    $files = $item->getFiles();
                    foreach ($files as $file) {
                        $qdc->appendNewElement('dc:identifier', $file->getWebPath('original'));
                    }
                }
            }
        }

        /* Handle some of the Item Type Metadata fields used in Finnish libraries*/

        $itemTypeFields = $this->availableItemtypeFields();

        if (in_array("YKL", $itemTypeFields)) {
            $dcClassifications = $item->getElementTexts('Item Type Metadata','YKL');
            foreach($dcClassifications as $dcClassification)
            {
                if (is_numeric(substr(trim($dcClassification->text), 0, 1)) & !preg_match('/[A-Za-z]/', $dcClassification->text) 
                    & (substr($dcClassification->text,0,2) != '19') &  (substr($dcClassification->text,0,2) != '18'))
                {
                    $ykl = $qdc->appendNewElement( 
                        'dc:subject', trim($dcClassification->text));
                    $ykl->setAttribute('scheme','YKL');
                } 
                
            }
        }
        if (in_array("Henkilö", $itemTypeFields)) {
            $dcPersonSubjects = $item->getElementTexts('Item Type Metadata','Henkilö');
            foreach($dcPersonSubjects as $dcPersonSubject)
            {
                $qdc->appendNewElement('dc:subject', trim($dcPersonSubject->text)); 
                            
            }
        }
        if (in_array("Aikamääre", $itemTypeFields)) {
            $dcEDTFDates = $item->getElementTexts('Item Type Metadata','Aikamääre');
                foreach($dcEDTFDates as $dcEDTFDate)
                {
                    $edtf = $qdc->appendNewElement('dcterms:created', trim($dcEDTFDate->text), 'dcterms:EDTF'); 
                    $edtf->setAttribute('scheme', 'EDTF');
                }
        }

        if (in_array("Porstua-luokka", $itemTypeFields)) {
            $dcSubjects = $item->getElementTexts('Item Type Metadata','Porstua-luokka');
                foreach($dcSubjects as $dcSubject)
                {
                    $porstuaClass = $qdc->appendNewElement('dc:subject', trim($dcSubject->text)); 
                    $porstuaClass->setAttribute('scheme', 'porstua');
                }
        }

        if (in_array("Porstua-alaluokka", $itemTypeFields)) {
            $dcSubjects = $item->getElementTexts('Item Type Metadata','Porstua-alaluokka');
                foreach($dcSubjects as $dcSubject)
                {
                    $porstuaSubClass = $qdc->appendNewElement('dc:subject', trim($dcSubject->text), 'dcterms:porstua');
                    $porstuaSubClass->setAttribute('scheme', 'porstua'); 
                    
                }
        }

        if (in_array("Lehden nimi", $itemTypeFields)) {
            $dcCitations = $item->getElementTexts('Item Type Metadata','Lehden nimi');
                foreach($dcCitations as $dcCitation)
                {
                    $qdc->appendNewElement('dcterms:bibliographicCitation', trim($dcCitation->text)); 
                    
                }
        }

        if (in_array("Aineistoryhmä", $itemTypeFields)) {
            $dcSubjects = $item->getElementTexts('Item Type Metadata','Aineistoryhmä');
                foreach($dcSubjects as $dcSubject)
                {
                    $itemgroup = $this->appendNewElement('dc:subject', trim($dcSubject->text)); 
                    $itemgroup->setAttribute('scheme', 'kirjastovirma');
                }
        }

        if (in_array("Aihekokonaisuus", $itemTypeFields)) {
            $dcSubjects = $item->getElementTexts('Item Type Metadata','Aihekokonaisuus');
                foreach($dcSubjects as $dcSubject)
                {
                    $subjectgroup = $this->appendNewElement('dc:subject', trim($dcSubject->text), 'dcterms:kirjastovirma'); 
                    $subjectgroup->setAttribute('scheme', 'kirjastovirma');
                }
        }

        if (in_array("Paikka", $itemTypeFields)) {
            $dcLocations = $item->getElementTexts('Item Type Metadata','Paikka');
                foreach($dcLocations as $dcLocation)
                {
                    $this->appendNewElement('dcterms:coverage', trim($dcLocation->text)); 
                    
                }
        }

        
        /* Handle itemtype if empty in metadata */
        
        $dcTypes = $item->getElementTexts('Dublin Core', 'Type');
        $itemType = $item->getItemType();

        if(empty($dcTypes) & !empty($itemType))
            {
                $itemTypeName = $itemType->name;
                $qdc->appendNewElement(
                            'dc:type', $this->translateItemType(mb_strtolower(trim($itemTypeName), 'UTF-8'))); 
            }
        else
        {   
         foreach($dcTypes as $dcType)
            {
                 if($dcType->text)
                 {
                    $itemtype = $dcType->text; 
                    $qdc->appendNewElement( 
                            'dc:type', $this->translateItemType(mb_strtolower(trim($itemtype), 'UTF-8')));   
                 }
         
         
            }
        }



        // Fields for Finna

        $dcIdentifiers = $item->getElementTexts(
                'Dublin Core','Identifier');
            foreach($dcIdentifiers as $dcIdentifier)
            {
                if (substr($dcIdentifier->text, 0, 3) == 'URN') {
                   $urn = $qdc->appendNewElement( 
                    'kk:permaddress','http://www.urn.fi/' . trim($dcIdentifier->text));
                   $urn->setAttribute('type', 'urn');

                } 
                
            }

        if (get_option('oaipmh_repository_expose_files') && metadata($item, 'has files')) {
            $files = $item->getFiles();
                foreach ($files as $file) {
                    $original = $qdc->appendNewElement('kk:file', $file->getWebPath('original'));
                    $original->setAttribute('bundle','ORIGINAL');
                    if($file->hasThumbnail()) {
                        $thumbnail = $qdc->appendNewElement('kk:file', $file->getWebPath('thumbnail'));
                        $thumbnail->setAttribute('bundle','THUMBNAIL');
                    }
                }
            }

    }

    protected function standardizeLanguage($rawLanguage)
    {           
                switch($rawLanguage)
                    {
                        case 'suomi':
                        case 'fi':
                            $language = 'fin';
                            break;
                        case 'englanti':
                        case 'en':
                            $language = 'eng';
                            break;
                        case 'ruotsi':
                        case 'sv':
                            $language = 'swe';
                            break;
                        case 'espanja':
                        case 'es':
                            $language = 'spa';
                            break;
                        case 'hollanti':
                        case 'nl':
                            $language = 'dut';
                            break;
                        case 'italia':
                        case 'it':
                            $language = 'ita';
                            break;
                        case 'latina':
                        case 'la':
                            $language = 'lat';
                            break;
                        case 'venäjä':
                        case 'ru':
                            $language = 'rus';
                            break;
                        case 'ranska':
                        case 'fr':
                            $language = 'fre';
                            break;
                        case 'saksa':
                        case 'de':
                            $language = 'ger';
                            break;
                        case 'viro':
                        case 'et':
                            $language = 'est';
                            break;
                    
                        default:
                            $language = $rawLanguage;
                    } 
                
                    return $language;

    }

    protected function translateItemType($itemtype)
     {
         switch($itemtype)
                    {
                        case 'document':
                            $itemtype = 'Text';
                            break;
                        case 'still image':
                            $itemtype = 'StillImage';
                            break;
                        case 'artikkeli':
                            $itemtype = 'Text';
                            break;
                        case 'artikkeliviite':
                            $itemtype = 'Text';
                            break;
                        case 'website':
                            $itemtype = 'Text';
                            break;
                        case 'linkki':
                            $itemtype = 'Text';
                            break;
                        case 'kirje':
                            $itemtype = 'Text';
                            break;
                        case 'käsikirjoitus':
                            $itemtype = 'Text';
                            break;
                        case 'rakennuspiirustus':
                            $itemtype = 'Image';
                            break;
                        case 'epub':
                            $itemtype = 'Text';
                            break;
                        case 'teksti':
                            $itemtype = 'Text';
                            break;
                        case 'kuva':
                            $itemtype = 'Image';
                            break;
                        case 'ääni':
                            $itemtype = 'Sound';
                            break;
                        default:
                            $itemtype = 'Text';
                    }
                    
            return str_replace(' ', '', ucwords($itemtype));
    }

    protected function availableItemtypeFields()
    {
        $db = Zend_Registry::get('bootstrap')->getResource('db');
        $table = $db->getTable('Element');
        $select = $table->getSelect();
        $itemTypeElementsAvailable = $db->fetchAll($select);
        $elementNames = array();
        foreach($itemTypeElementsAvailable as $key => $value)
        {   
            array_push($elementNames,$itemTypeElementsAvailable[$key]['name']);

        }
    
        return $elementNames;

    }

}
